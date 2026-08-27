<?php

class TrainingAdminController {
    private $areaModel;
    private $courseModel;
    private $taskModel;
    private $rewardModel;
    private $enrollmentModel;
    private $userModel;
    private $companyModel;
    private $resourceModel;
    private $discussionModel;

    public function __construct() {
        if (!hasRole('admin')) {
            redirect('login');
        }
        if (!learning_is_ready()) {
            $_SESSION['flash_error'] = 'Ejecutá migration_learning.sql en MySQL (ver MIGRATIONS.md).';
            redirect('admin/dashboard');
        }
        $this->areaModel = new Area();
        $this->courseModel = new Course();
        $this->taskModel = new Task();
        $this->rewardModel = new Reward();
        $this->enrollmentModel = new CourseEnrollment();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->resourceModel = new CourseResource();
        $this->discussionModel = new CourseDiscussion();
    }

    private function companyId() {
        return requireAdminCompany('admin/dashboard');
    }

    /** Sube video (mp4/webm) o PDF a public/uploads/courses/{courseId}/ */
    private function uploadLessonFile($courseId, $contentType) {
        if (empty($_FILES['content_file']['name']) || ($_FILES['content_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($_FILES['content_file']['name'], PATHINFO_EXTENSION));
        $videoExt = ['mp4', 'webm', 'ogg'];
        $fileExt = ['pdf', 'xls', 'xlsx', 'doc', 'docx', 'pptx', 'zip', 'csv'];
        if ($contentType === 'video' && !in_array($ext, $videoExt, true)) {
            $_SESSION['flash_error'] = 'Para video subí MP4, WebM u OGG.';
            return false;
        }
        if ($contentType === 'file' && !in_array($ext, $fileExt, true)) {
            $_SESSION['flash_error'] = 'Formato no permitido. Usá PDF, Excel, Word, PPT o ZIP.';
            return false;
        }
        $maxMb = $contentType === 'video' ? 150 : 40;
        $videoMimes = ['video/mp4', 'video/webm', 'video/ogg'];
        $fileMimes = uploads_flat_mimes(uploads_document_mimes());
        $fileMimes = array_merge($fileMimes, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'text/csv',
            'text/plain',
        ]);
        $valid = uploads_validate_uploaded_file(
            $_FILES['content_file'],
            $contentType === 'video' ? $videoExt : $fileExt,
            $contentType === 'video' ? $videoMimes : $fileMimes,
            $maxMb * 1024 * 1024
        );
        if (!$valid['ok']) {
            $_SESSION['flash_error'] = $valid['message'];
            return false;
        }
        $ext = $valid['ext'];
        if (function_exists('uploads_ensure_private_directory')) {
            uploads_ensure_private_directory('courses/' . (int)$courseId);
        }
        $dir = APPROOT . '/../public/uploads/courses/' . (int)$courseId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $_SESSION['flash_error'] = 'No se pudo crear la carpeta de uploads.';
            return false;
        }
        $filename = 'lesson-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($_FILES['content_file']['tmp_name'], $dest)) {
            $_SESSION['flash_error'] = 'Error al guardar el archivo.';
            return false;
        }
        return 'courses/' . (int)$courseId . '/' . $filename;
    }

    public function index() {
        redirect('trainingAdmin/courses');
    }

    // --- Áreas ---
    public function areas() {
        $this->companyId();
        $this->view('admin/training/areas', [
            'areas' => $this->areaModel->getAll(false),
            'companies' => $this->companyModel->getAllCompanies(),
            'global_areas' => $this->areaModel->isGlobalAreasReady(),
            'area_show_overtime_column' => $this->areaModel->hasShowOvertimeColumn(),
            'area_show_cp_extras_column' => $this->areaModel->hasShowCpExtrasColumn(),
        ]);
    }

    public function saveArea() {
        csrf_verify();
        $sessionCompanyId = $this->companyId();
        $id = (int)($_POST['id'] ?? 0);
        $globalReady = $this->areaModel->isGlobalAreasReady();

        if (!empty($_POST['toggle_only']) && $id > 0) {
            $area = $this->areaModel->getById($id);
            if ($area) {
                $active = isset($_POST['is_active']) && (int)$_POST['is_active'] === 1;
                $this->areaModel->update($id, $area->name, $active, false);
                $_SESSION['flash_success'] = $active ? 'Área activada.' : 'Área desactivada.';
            }
            redirect('trainingAdmin/areas');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['flash_error'] = 'El nombre del área es obligatorio.';
            redirect('trainingAdmin/areas');
        }

        $companyId = $this->resolveAreaCompanyIdFromPost($globalReady, $sessionCompanyId);

        if ($id > 0) {
            $area = $this->areaModel->getById($id);
            if (!$area) {
                redirect('trainingAdmin/areas');
            }
            $isActive = isset($_POST['is_active']) ? !empty($_POST['is_active']) : (bool)$area->is_active;
            $updateCompany = $globalReady ? $companyId : false;
            $showOt = false;
            $showCp = false;
            if ($this->areaModel->hasShowOvertimeColumn() && array_key_exists('show_overtime', $_POST)) {
                $raw = $_POST['show_overtime'];
                if ($raw === '' || $raw === 'inherit') {
                    $showOt = null;
                } else {
                    $showOt = ($raw === '1' || $raw === 1);
                }
            }
            if ($this->areaModel->hasShowCpExtrasColumn() && array_key_exists('show_cp_extras', $_POST)) {
                $rawCp = $_POST['show_cp_extras'];
                if ($rawCp === '' || $rawCp === 'inherit') {
                    $showCp = null;
                } else {
                    $showCp = ($rawCp === '1' || $rawCp === 1);
                }
            }
            if ($this->areaModel->update($id, $name, $isActive, $updateCompany, $showOt, $showCp)) {
                $_SESSION['flash_success'] = 'Área actualizada.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo actualizar (¿nombre duplicado en ese alcance?).';
            }
        } else {
            if ($this->areaModel->create($name, $companyId)) {
                $_SESSION['flash_success'] = 'Área creada.';
            } else {
                $_SESSION['flash_error'] = 'No se pudo crear el área (¿nombre duplicado en ese alcance?).';
            }
        }
        redirect('trainingAdmin/areas');
    }

    /** NULL = todas las empresas; int = solo esa empresa. */
    private function resolveAreaCompanyIdFromPost($globalReady, $sessionCompanyId) {
        if (!$globalReady) {
            return (int)$sessionCompanyId;
        }
        $scope = $_POST['area_scope'] ?? 'all';
        if ($scope === 'company') {
            $cid = (int)($_POST['company_id'] ?? 0);
            if ($cid <= 0) {
                $_SESSION['flash_error'] = 'Seleccioná la empresa para un área de alcance limitado.';
                redirect('trainingAdmin/areas');
            }
            return $cid;
        }
        return null;
    }

    // --- Cursos ---
    public function courses() {
        $cid = $this->companyId();
        $this->view('admin/training/courses', [
            'courses' => $this->courseModel->getByCompany($cid, false),
        ]);
    }

    public function courseEdit($id = 0) {
        $cid = $this->companyId();
        $id = (int)$id;
        $course = $id ? $this->courseModel->getById($id) : null;
        if ($id && (!$course || (int)$course->company_id !== $cid)) {
            redirect('trainingAdmin/courses');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $data = [
                'company_id' => $cid,
                'area_id' => (int)($_POST['area_id'] ?? 0),
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'thumbnail_url' => $_POST['thumbnail_url'] ?? '',
                'stars_on_complete' => (int)($_POST['stars_on_complete'] ?? 5),
                'first_finisher_bonus' => (int)($_POST['first_finisher_bonus'] ?? 2),
                'passing_score' => (int)($_POST['passing_score'] ?? 70),
                'estimated_minutes' => (int)($_POST['estimated_minutes'] ?? 60),
                'duration_hours' => trim($_POST['duration_hours'] ?? ''),
                'certificate_valid_days' => trim($_POST['certificate_valid_days'] ?? ''),
                'require_quiz' => !empty($_POST['require_quiz']),
                'max_quiz_attempts' => (int)($_POST['max_quiz_attempts'] ?? 3),
                'is_published' => !empty($_POST['is_published']),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'created_by' => $_SESSION['user_id'],
            ];
            if (trim($data['title']) === '') {
                $_SESSION['flash_error'] = 'El título es obligatorio.';
                redirect('trainingAdmin/courseEdit/' . $id);
            }
            if ($id) {
                $wasPublished = $course && (int)$course->is_published === 1;
                $this->courseModel->update($id, $data);
                if (!empty($data['is_published']) && !$wasPublished) {
                    notify_course_published($id);
                }
                $_SESSION['flash_success'] = 'Curso actualizado.';
                redirect('trainingAdmin/courseEdit/' . $id);
            }
            $newId = $this->courseModel->create($data);
            if (!empty($data['is_published'])) {
                notify_course_published($newId);
            }
            $_SESSION['flash_success'] = 'Curso creado. Agregá lecciones y preguntas.';
            redirect('trainingAdmin/courseEdit/' . $newId);
        }
        $resources = [];
        $discussions = [];
        $pendingQuestions = 0;
        if ($id && learning_enrich_is_ready()) {
            $resources = $this->resourceModel->getAllByCourse($id);
            $discussions = $this->discussionModel->getByCourse($id);
            $pendingQuestions = $this->discussionModel->getPendingCount($id);
        }
        $this->view('admin/training/course_workspace', [
            'course' => $course,
            'areas' => $this->areaModel->getByCompany($cid),
            'lessons' => $id ? $this->courseModel->getLessons($id) : [],
            'questions' => $id ? $this->courseModel->getQuizQuestions($id) : [],
            'assignments' => $id ? $this->courseModel->getAssignments($id) : [],
            'users' => $this->userModel->getUsersByCompany($cid),
            'resources' => $resources,
            'discussions' => $discussions,
            'pending_questions' => $pendingQuestions,
            'enrich_ready' => learning_enrich_is_ready(),
            'active_tab' => $_GET['tab'] ?? 'overview',
        ]);
    }

    public function saveLesson($courseId) {
        csrf_verify();
        $cid = $this->companyId();
        $courseId = (int)$courseId;
        $course = $this->courseModel->getById($courseId);
        if (!$course || (int)$course->company_id !== $cid) {
            redirect('trainingAdmin/courses');
        }
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $contentType = $_POST['content_type'] ?? 'text';
        $existing = $lessonId ? $this->courseModel->getLessonById($lessonId) : null;

        $data = [
            'position' => (int)($_POST['position'] ?? 1),
            'title' => $_POST['title'] ?? '',
            'content_type' => $contentType,
            'content_url' => trim($_POST['content_url'] ?? ''),
            'content_body' => $_POST['content_body'] ?? '',
            'objectives' => $_POST['objectives'] ?? '',
            'instructor_notes' => $_POST['instructor_notes'] ?? '',
            'key_points' => $_POST['key_points'] ?? '',
            'duration_minutes' => (int)($_POST['duration_minutes'] ?? 5),
            'is_required' => !empty($_POST['is_required']),
        ];

        $uploaded = $this->uploadLessonFile($courseId, $contentType);
        if ($uploaded === false) {
            redirect('trainingAdmin/courseEdit/' . $courseId);
        }
        if ($uploaded) {
            $data['content_url'] = $uploaded;
        } elseif ($contentType === 'video' && $data['content_url'] === '' && $existing) {
            $data['content_url'] = $existing->content_url ?? '';
        } elseif ($contentType === 'file' && $data['content_url'] === '' && $existing) {
            $data['content_url'] = $existing->content_url ?? '';
        }

        if ($contentType === 'text') {
            $data['content_url'] = '';
        }

        $savedId = $this->courseModel->saveLesson($courseId, $data, $lessonId ?: null);
        $_SESSION['flash_success'] = 'Lección guardada.';
        $lid = $lessonId ?: (is_int($savedId) ? $savedId : 0);
        redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=lesson&edit_lesson=' . (int)$lid);
    }

    public function previewLesson($courseId, $lessonId) {
        $cid = $this->companyId();
        $courseId = (int)$courseId;
        $lessonId = (int)$lessonId;
        $course = $this->courseModel->getById($courseId);
        $lesson = $this->courseModel->getLessonById($lessonId);
        if (!$course || (int)$course->company_id !== $cid || !$lesson || (int)$lesson->course_id !== $courseId) {
            redirect('trainingAdmin/courseEdit/' . $courseId);
        }
        $resources = learning_enrich_is_ready()
            ? $this->resourceModel->getByCourse($courseId, $lessonId)
            : [];
        $this->view('admin/training/lesson_preview', [
            'course' => $course,
            'lesson' => $lesson,
            'resources' => $resources,
            'lessons' => $this->courseModel->getLessons($courseId),
        ]);
    }

    public function saveResource($courseId) {
        csrf_verify();
        $cid = $this->companyId();
        $courseId = (int)$courseId;
        $course = $this->courseModel->getById($courseId);
        if (!$course || (int)$course->company_id !== $cid || !learning_enrich_is_ready()) {
            redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=materials');
        }
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $_SESSION['flash_error'] = 'El título del material es obligatorio.';
            redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=materials');
        }
        $resourceType = $_POST['resource_type'] ?? 'document';
        $externalUrl = trim($_POST['external_url'] ?? '');
        $filePath = null;
        $fileName = null;
        $fileSize = null;

        if (!empty($_FILES['resource_file']['name']) && ($_FILES['resource_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $upload = $this->uploadResourceFile($courseId, $_FILES['resource_file']);
            if ($upload === false) {
                redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=materials');
            }
            if ($upload) {
                $filePath = $upload['path'];
                $fileName = $upload['name'];
                $fileSize = $upload['size'];
                $resourceType = $upload['type'];
            }
        } elseif ($externalUrl !== '') {
            $resourceType = in_array($resourceType, ['link', 'video'], true) ? $resourceType : 'link';
            if (learning_is_embed_video_url($externalUrl)) {
                $resourceType = 'video';
            }
        } else {
            $_SESSION['flash_error'] = 'Subí un archivo o indicá un enlace.';
            redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=materials');
        }

        $this->resourceModel->create([
            'course_id' => $courseId,
            'lesson_id' => (int)($_POST['lesson_id'] ?? 0) ?: null,
            'title' => $title,
            'description' => $_POST['description'] ?? '',
            'resource_type' => $resourceType,
            'external_url' => $externalUrl ?: null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_visible' => !empty($_POST['is_visible']),
            'created_by' => $_SESSION['user_id'],
        ]);
        $_SESSION['flash_success'] = 'Material agregado.';
        if (!empty($_POST['lesson_id'])) {
            redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=lesson&edit_lesson=' . (int)$_POST['lesson_id']);
        }
        redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=materials');
    }

    private function uploadResourceFile($courseId, array $file) {
        $allowedExt = learning_allowed_upload_extensions();
        $fileMimes = uploads_flat_mimes(uploads_document_mimes());
        $fileMimes = array_merge($fileMimes, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'text/csv',
            'text/plain',
        ]);
        $valid = uploads_validate_uploaded_file($file, $allowedExt, $fileMimes, 40 * 1024 * 1024);
        if (!$valid['ok']) {
            $_SESSION['flash_error'] = $valid['message'];
            return false;
        }
        $ext = $valid['ext'];
        uploads_ensure_private_directory('courses/' . (int)$courseId . '/materials');
        $dir = APPROOT . '/../public/uploads/courses/' . (int)$courseId . '/materials';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $_SESSION['flash_error'] = 'No se pudo crear la carpeta de materiales.';
            return false;
        }
        $filename = 'res-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $_SESSION['flash_error'] = 'Error al subir el archivo.';
            return false;
        }
        return [
            'path' => 'courses/' . (int)$courseId . '/materials/' . $filename,
            'name' => $file['name'],
            'size' => (int)$file['size'],
            'type' => learning_ext_to_resource_type($ext),
        ];
    }

    public function deleteResource($courseId, $resourceId) {
        csrf_verify();
        $cid = $this->companyId();
        $courseId = (int)$courseId;
        $course = $this->courseModel->getById($courseId);
        if ($course && (int)$course->company_id === $cid && learning_enrich_is_ready()) {
            $this->resourceModel->delete((int)$resourceId, $courseId);
            $_SESSION['flash_success'] = 'Material eliminado.';
        }
        redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=materials');
    }

    public function replyDiscussion($discussionId) {
        csrf_verify();
        $cid = $this->companyId();
        $discussion = $this->discussionModel->getById((int)$discussionId);
        if (!$discussion || (int)$discussion->company_id !== $cid) {
            redirect('trainingAdmin/courses');
        }
        $reply = trim($_POST['admin_reply'] ?? '');
        if ($reply === '') {
            $_SESSION['flash_error'] = 'Escribí una respuesta.';
        } else {
            $this->discussionModel->reply((int)$discussionId, (int)$_SESSION['user_id'], $reply, !empty($_POST['is_resolved']));
            $_SESSION['flash_success'] = 'Respuesta publicada.';
        }
        redirect('trainingAdmin/courseEdit/' . (int)$discussion->course_id . '?tab=community');
    }

    public function deleteLesson($courseId, $lessonId) {
        csrf_verify();
        $cid = $this->companyId();
        $course = $this->courseModel->getById((int)$courseId);
        if ($course && (int)$course->company_id === $cid) {
            $this->courseModel->deleteLesson((int)$lessonId, (int)$courseId);
            $_SESSION['flash_success'] = 'Lección eliminada.';
        }
        redirect('trainingAdmin/courseEdit/' . (int)$courseId . '?tab=lessons');
    }

    public function saveQuiz($courseId) {
        csrf_verify();
        $cid = $this->companyId();
        $courseId = (int)$courseId;
        $course = $this->courseModel->getById($courseId);
        if (!$course || (int)$course->company_id !== $cid) {
            redirect('trainingAdmin/courses');
        }
        $qid = (int)($_POST['question_id'] ?? 0);
        $options = [];
        $texts = $_POST['option_text'] ?? [];
        $correct = (int)($_POST['correct_option'] ?? 0);
        if (is_array($texts)) {
            $i = 0;
            foreach ($texts as $t) {
                if (trim($t) === '') {
                    $i++;
                    continue;
                }
                $options[] = ['text' => $t, 'correct' => ($i === $correct)];
                $i++;
            }
        }
        $this->courseModel->saveQuizQuestion($courseId, [
            'position' => (int)($_POST['position'] ?? 1),
            'question_text' => $_POST['question_text'] ?? '',
            'explanation' => $_POST['explanation'] ?? '',
            'options' => $options,
        ], $qid ?: null);
        $_SESSION['flash_success'] = 'Pregunta guardada.';
        redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=quiz');
    }

    public function deleteQuiz($courseId, $questionId) {
        csrf_verify();
        $cid = $this->companyId();
        $course = $this->courseModel->getById((int)$courseId);
        if ($course && (int)$course->company_id === $cid) {
            $this->courseModel->deleteQuizQuestion((int)$questionId, (int)$courseId);
        }
        redirect('trainingAdmin/courseEdit/' . (int)$courseId . '?tab=quiz');
    }

    public function saveAssignments($courseId) {
        csrf_verify();
        $cid = $this->companyId();
        $courseId = (int)$courseId;
        $course = $this->courseModel->getById($courseId);
        if (!$course || (int)$course->company_id !== $cid) {
            redirect('trainingAdmin/courses');
        }
        $rows = [];
        if (!empty($_POST['assign_company'])) {
            $rows[] = ['target_type' => 'company', 'target_id' => $cid, 'due_date' => $_POST['due_date'] ?? null];
        }
        foreach ($_POST['area_ids'] ?? [] as $aid) {
            $rows[] = ['target_type' => 'area', 'target_id' => (int)$aid, 'due_date' => $_POST['due_date'] ?? null];
        }
        foreach ($_POST['user_ids'] ?? [] as $uid) {
            $rows[] = ['target_type' => 'user', 'target_id' => (int)$uid, 'due_date' => $_POST['due_date'] ?? null];
        }
        if (empty($rows)) {
            $_SESSION['flash_error'] = 'Seleccioná al menos una empresa, área o usuario.';
            redirect('trainingAdmin/courseEdit/' . $courseId);
        }
        if ($course->require_quiz && $this->courseModel->countQuizQuestions($courseId) < 5) {
            $_SESSION['flash_error'] = 'Publicá al menos 5 preguntas antes de asignar con cuestionario obligatorio.';
            redirect('trainingAdmin/courseEdit/' . $courseId);
        }
        $this->courseModel->replaceAssignments($courseId, $rows);
        if ((int)$course->is_published === 1) {
            notify_course_published($courseId);
        }
        $_SESSION['flash_success'] = 'Asignaciones guardadas.';
        redirect('trainingAdmin/courseEdit/' . $courseId . '?tab=assign');
    }

    public function reports() {
        $cid = $this->companyId();
        $this->view('admin/training/reports', [
            'rows' => $this->enrollmentModel->getReportByCompany($cid),
        ]);
    }

    // --- Premios ---
    public function rewards() {
        $cid = $this->companyId();
        $this->view('admin/training/rewards', [
            'rewards' => $this->rewardModel->getByCompany($cid, false),
            'pending' => $this->rewardModel->getPendingRedemptions($cid),
        ]);
    }

    public function saveReward() {
        csrf_verify();
        $cid = $this->companyId();
        $data = [
            'company_id' => $cid,
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'stars_required' => (int)($_POST['stars_required'] ?? 100),
            'is_active' => !empty($_POST['is_active']),
        ];
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            if (!$this->rewardModel->update($id, $data)) {
                $_SESSION['flash_error'] = 'Premio no encontrado en tu empresa.';
                redirect('trainingAdmin/rewards');
            }
        } else {
            $this->rewardModel->create($data);
        }
        $_SESSION['flash_success'] = 'Premio guardado.';
        redirect('trainingAdmin/rewards');
    }

    public function reviewRedemption($id, $status) {
        csrf_verify();
        $cid = $this->companyId();
        $st = $status === 'approved' ? 'approved' : 'rejected';
        if (!$this->rewardModel->reviewRedemption((int)$id, $st, $cid)) {
            $_SESSION['flash_error'] = 'Canje no encontrado.';
            redirect('trainingAdmin/rewards');
        }
        $_SESSION['flash_success'] = 'Canje actualizado.';
        redirect('trainingAdmin/rewards');
    }

    // --- Tareas ---
    public function tasks() {
        $cid = $this->companyId();
        $this->view('admin/training/tasks', [
            'tasks' => $this->taskModel->getByCompany($cid),
            'areas' => $this->areaModel->getByCompany($cid),
            'users' => $this->userModel->getUsersByCompany($cid),
        ]);
    }

    public function saveTask() {
        csrf_verify();
        $cid = $this->companyId();
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'company_id' => $cid,
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'due_date' => $_POST['due_date'] ?? null,
            'stars_on_complete' => (int)($_POST['stars_on_complete'] ?? 0),
            'is_active' => !empty($_POST['is_active']),
            'created_by' => $_SESSION['user_id'],
        ];
        if ($id) {
            $this->taskModel->update($id, $data);
            $taskId = $id;
        } else {
            $taskId = $this->taskModel->create($data);
        }
        $rows = [];
        if (!empty($_POST['assign_company'])) {
            $rows[] = ['target_type' => 'company', 'target_id' => $cid];
        }
        foreach ($_POST['area_ids'] ?? [] as $aid) {
            $rows[] = ['target_type' => 'area', 'target_id' => (int)$aid];
        }
        foreach ($_POST['user_ids'] ?? [] as $uid) {
            $rows[] = ['target_type' => 'user', 'target_id' => (int)$uid];
        }
        if ($taskId && !empty($rows)) {
            $this->taskModel->replaceAssignments($taskId, $rows);
        }
        $_SESSION['flash_success'] = 'Tarea guardada.';
        redirect('trainingAdmin/tasks');
    }

    public function streamResource($id = 0) {
        $resource = $this->resourceModel->getById((int)$id);
        if (!$resource || empty($resource->file_path)) {
            http_response_code(404);
            exit;
        }
        $course = $this->courseModel->getById((int)$resource->course_id);
        if (!$course || (int)$course->company_id !== $this->companyId()) {
            http_response_code(403);
            exit;
        }
        $inline = !preg_match('/\.(xlsx?|csv|docx?|pptx?|zip|rar)$/i', (string)$resource->file_path);
        protected_upload_send($resource->file_path, $inline, basename((string)$resource->file_path));
    }

    public function streamLessonFile($courseId = 0, $lessonId = 0) {
        $courseId = (int)$courseId;
        $lessonId = (int)$lessonId;
        $course = $this->courseModel->getById($courseId);
        if (!$course || (int)$course->company_id !== $this->companyId()) {
            http_response_code(403);
            exit;
        }
        $lesson = $this->courseModel->getLessonById($lessonId);
        if (!$lesson || (int)$lesson->course_id !== $courseId) {
            http_response_code(404);
            exit;
        }
        $url = trim((string)($lesson->content_url ?? ''));
        if ($url === '' || preg_match('#^https?://#i', $url) || learning_is_embed_video_url($url)) {
            http_response_code(404);
            exit;
        }
        $inline = !preg_match('/\.(xlsx?|csv|docx?|pptx?|zip|rar)$/i', $url);
        protected_upload_send($url, $inline, basename($url));
    }

    private function view($view, $data = []) {
        if (file_exists('../app/views/' . $view . '.php')) {
            require_once '../app/views/' . $view . '.php';
        } else {
            die('Vista no encontrada: ' . $view);
        }
    }
}
