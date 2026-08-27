<?php

class Course {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function isSchemaReady() {
        return learning_is_ready();
    }

    public function getByCompany($companyId, $publishedOnly = false) {
        $sql = 'SELECT c.*, a.name AS area_name,
                (SELECT COUNT(*) FROM course_lessons cl WHERE cl.course_id = c.id) AS lesson_count,
                (SELECT COUNT(*) FROM course_quiz_questions qq WHERE qq.course_id = c.id) AS question_count
            FROM courses c
            LEFT JOIN areas a ON a.id = c.area_id
            WHERE c.company_id = :company_id';
        if ($publishedOnly) {
            $sql .= ' AND c.is_published = 1';
        }
        $sql .= ' ORDER BY c.sort_order ASC, c.title ASC';
        $this->db->query($sql);
        $this->db->bind(':company_id', (int)$companyId);
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT c.*, a.name AS area_name FROM courses c
            LEFT JOIN areas a ON a.id = c.area_id WHERE c.id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function create(array $data) {
        $slug = !empty($data['slug']) ? $data['slug'] : learning_slugify($data['title']);
        $this->db->query('INSERT INTO courses (
            company_id, area_id, title, slug, description, thumbnail_url,
            stars_on_complete, first_finisher_bonus, passing_score, estimated_minutes, require_quiz,
            max_quiz_attempts, is_published, sort_order, duration_hours, certificate_valid_days, created_by
        ) VALUES (
            :company_id, :area_id, :title, :slug, :description, :thumbnail_url,
            :stars_on_complete, :first_finisher_bonus, :passing_score, :estimated_minutes, :require_quiz,
            :max_quiz_attempts, :is_published, :sort_order, :duration_hours, :certificate_valid_days, :created_by
        )');
        $this->bindCourseFields($data, $slug);
        if ($this->db->execute()) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }

    public function update($id, array $data) {
        $slug = !empty($data['slug']) ? $data['slug'] : learning_slugify($data['title']);
        $this->db->query('UPDATE courses SET
            area_id = :area_id, title = :title, slug = :slug, description = :description,
            thumbnail_url = :thumbnail_url, stars_on_complete = :stars_on_complete,
            first_finisher_bonus = :first_finisher_bonus,
            passing_score = :passing_score, estimated_minutes = :estimated_minutes,
            require_quiz = :require_quiz, max_quiz_attempts = :max_quiz_attempts,
            is_published = :is_published, sort_order = :sort_order,
            duration_hours = :duration_hours, certificate_valid_days = :certificate_valid_days
            WHERE id = :course_id AND company_id = :p_company_id');
        $this->bindCourseFields($data, $slug);
        $this->db->bind(':course_id', (int)$id);
        $this->db->bind(':p_company_id', (int)$data['company_id']);
        return $this->db->execute();
    }

    private function bindCourseFields(array $data, $slug) {
        $this->db->bind(':company_id', (int)$data['company_id']);
        $this->db->bind(':area_id', !empty($data['area_id']) ? (int)$data['area_id'] : null);
        $this->db->bind(':title', trim($data['title']));
        $this->db->bind(':slug', $slug);
        $this->db->bind(':description', $data['description'] ?? null);
        $this->db->bind(':thumbnail_url', $data['thumbnail_url'] ?? null);
        $this->db->bind(':stars_on_complete', (int)($data['stars_on_complete'] ?? 5));
        $this->db->bind(':first_finisher_bonus', (int)($data['first_finisher_bonus'] ?? 2));
        $this->db->bind(':passing_score', (int)($data['passing_score'] ?? 70));
        $this->db->bind(':estimated_minutes', (int)($data['estimated_minutes'] ?? 60));
        $this->db->bind(':require_quiz', !empty($data['require_quiz']) ? 1 : 0);
        $this->db->bind(':max_quiz_attempts', (int)($data['max_quiz_attempts'] ?? 3));
        $this->db->bind(':is_published', !empty($data['is_published']) ? 1 : 0);
        $this->db->bind(':sort_order', (int)($data['sort_order'] ?? 0));
        $this->db->bind(':duration_hours', ($data['duration_hours'] ?? '') !== '' ? (float)$data['duration_hours'] : null);
        $this->db->bind(':certificate_valid_days', ($data['certificate_valid_days'] ?? '') !== '' ? (int)$data['certificate_valid_days'] : null);
        $this->db->bind(':created_by', !empty($data['created_by']) ? (int)$data['created_by'] : null);
    }

    public function delete($id, $companyId) {
        $this->db->query('DELETE FROM courses WHERE id = :course_id AND company_id = :p_company_id');
        $this->db->bind(':course_id', (int)$id);
        $this->db->bind(':p_company_id', (int)$companyId);
        return $this->db->execute();
    }

    public function getLessons($courseId) {
        $this->db->query('SELECT * FROM course_lessons WHERE course_id = :cid ORDER BY position ASC');
        $this->db->bind(':cid', (int)$courseId);
        return $this->db->resultSet();
    }

    public function getLessonByPosition($courseId, $position) {
        $this->db->query('SELECT * FROM course_lessons WHERE course_id = :cid AND position = :pos LIMIT 1');
        $this->db->bind(':cid', (int)$courseId);
        $this->db->bind(':pos', (int)$position);
        return $this->db->single();
    }

    public function getLessonById($lessonId) {
        $this->db->query('SELECT * FROM course_lessons WHERE id = :id');
        $this->db->bind(':id', (int)$lessonId);
        return $this->db->single();
    }

    public function saveLesson($courseId, array $data, $lessonId = null) {
        $enrich = learning_enrich_is_ready();
        if ($lessonId) {
            $sql = 'UPDATE course_lessons SET position = :position, title = :title,
                content_type = :content_type, content_url = :content_url, content_body = :content_body';
            if ($enrich) {
                $sql .= ', objectives = :objectives, instructor_notes = :instructor_notes, key_points = :key_points';
            }
            $sql .= ', duration_minutes = :duration_minutes, is_required = :is_required
                WHERE id = :id AND course_id = :course_id';
            $this->db->query($sql);
            $this->db->bind(':id', (int)$lessonId);
        } else {
            $cols = 'course_id, position, title, content_type, content_url, content_body, duration_minutes, is_required';
            $vals = ':course_id, :position, :title, :content_type, :content_url, :content_body, :duration_minutes, :is_required';
            if ($enrich) {
                $cols .= ', objectives, instructor_notes, key_points';
                $vals .= ', :objectives, :instructor_notes, :key_points';
            }
            $this->db->query("INSERT INTO course_lessons ($cols) VALUES ($vals)");
        }
        $this->db->bind(':course_id', (int)$courseId);
        $this->db->bind(':position', (int)$data['position']);
        $this->db->bind(':title', trim($data['title']));
        $this->db->bind(':content_type', $data['content_type'] ?? 'text');
        $this->db->bind(':content_url', $data['content_url'] ?? null);
        $this->db->bind(':content_body', $data['content_body'] ?? null);
        if ($enrich) {
            $this->db->bind(':objectives', $data['objectives'] ?? null);
            $this->db->bind(':instructor_notes', $data['instructor_notes'] ?? null);
            $this->db->bind(':key_points', $data['key_points'] ?? null);
        }
        $this->db->bind(':duration_minutes', (int)($data['duration_minutes'] ?? 5));
        $this->db->bind(':is_required', !empty($data['is_required']) ? 1 : 0);
        if ($this->db->execute() && !$lessonId) {
            return (int)$this->db->lastInsertId();
        }
        return $lessonId ? true : false;
    }

    public function deleteLesson($lessonId, $courseId) {
        $this->db->query('DELETE FROM course_lessons WHERE id = :id AND course_id = :cid');
        $this->db->bind(':id', (int)$lessonId);
        $this->db->bind(':cid', (int)$courseId);
        return $this->db->execute();
    }

    public function getQuizQuestions($courseId) {
        $this->db->query('SELECT * FROM course_quiz_questions WHERE course_id = :cid ORDER BY position ASC');
        $this->db->bind(':cid', (int)$courseId);
        $questions = $this->db->resultSet();
        foreach ($questions as $q) {
            $q->options = $this->getQuizOptions($q->id);
            $q->display_position = (int)$q->position;
        }
        return $questions;
    }

    public function getQuizQuestionsForEnrollment($courseId, $enrollmentId) {
        $questions = $this->getQuizQuestions($courseId);
        $seed = (new CourseEnrollment())->ensureQuizSeed($enrollmentId);
        if ($seed) {
            return learning_shuffle_quiz_questions($questions, $seed);
        }
        return $questions;
    }

    public function getQuizOptions($questionId) {
        $this->db->query('SELECT * FROM course_quiz_options WHERE question_id = :qid ORDER BY id ASC');
        $this->db->bind(':qid', (int)$questionId);
        return $this->db->resultSet();
    }

    public function saveQuizQuestion($courseId, array $data, $questionId = null) {
        if ($questionId) {
            $this->db->query('UPDATE course_quiz_questions SET position = :position,
                question_text = :question_text, explanation = :explanation
                WHERE id = :id AND course_id = :course_id');
            $this->db->bind(':id', (int)$questionId);
        } else {
            $this->db->query('INSERT INTO course_quiz_questions (course_id, position, question_text, explanation)
                VALUES (:course_id, :position, :question_text, :explanation)');
        }
        $this->db->bind(':course_id', (int)$courseId);
        $this->db->bind(':position', (int)$data['position']);
        $this->db->bind(':question_text', trim($data['question_text']));
        $this->db->bind(':explanation', $data['explanation'] ?? null);
        if (!$this->db->execute()) {
            return false;
        }
        $qid = $questionId ?: (int)$this->db->lastInsertId();
        if ($questionId) {
            $this->db->query('DELETE FROM course_quiz_options WHERE question_id = :qid');
            $this->db->bind(':qid', (int)$questionId);
            $this->db->execute();
        }
        foreach ($data['options'] ?? [] as $opt) {
            $this->db->query('INSERT INTO course_quiz_options (question_id, option_text, is_correct)
                VALUES (:question_id, :option_text, :is_correct)');
            $this->db->bind(':question_id', $qid);
            $this->db->bind(':option_text', trim($opt['text']));
            $this->db->bind(':is_correct', !empty($opt['correct']) ? 1 : 0);
            $this->db->execute();
        }
        return $qid;
    }

    public function deleteQuizQuestion($questionId, $courseId) {
        $this->db->query('DELETE FROM course_quiz_questions WHERE id = :id AND course_id = :cid');
        $this->db->bind(':id', (int)$questionId);
        $this->db->bind(':cid', (int)$courseId);
        return $this->db->execute();
    }

    public function getAssignments($courseId) {
        $this->db->query('SELECT * FROM course_assignments WHERE course_id = :cid');
        $this->db->bind(':cid', (int)$courseId);
        return $this->db->resultSet();
    }

    public function replaceAssignments($courseId, array $rows) {
        $this->db->query('DELETE FROM course_assignments WHERE course_id = :cid');
        $this->db->bind(':cid', (int)$courseId);
        $this->db->execute();
        foreach ($rows as $row) {
            $this->db->query('INSERT INTO course_assignments (course_id, target_type, target_id, due_date)
                VALUES (:course_id, :target_type, :target_id, :due_date)');
            $this->db->bind(':course_id', (int)$courseId);
            $this->db->bind(':target_type', $row['target_type']);
            $this->db->bind(':target_id', (int)$row['target_id']);
            $this->db->bind(':due_date', !empty($row['due_date']) ? $row['due_date'] : null);
            $this->db->execute();
        }
        return true;
    }

    public function countRequiredLessons($courseId) {
        $this->db->query('SELECT COUNT(*) AS c FROM course_lessons WHERE course_id = :cid AND is_required = 1');
        $this->db->bind(':cid', (int)$courseId);
        $r = $this->db->single();
        return $r ? (int)$r->c : 0;
    }

    public function countQuizQuestions($courseId) {
        $this->db->query('SELECT COUNT(*) AS c FROM course_quiz_questions WHERE course_id = :cid');
        $this->db->bind(':cid', (int)$courseId);
        $r = $this->db->single();
        return $r ? (int)$r->c : 0;
    }
}
