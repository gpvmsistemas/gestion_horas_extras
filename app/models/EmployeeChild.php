<?php

/** Hijos/as registrados por colaborador (fecha de nacimiento y sexo). */
class EmployeeChild {
    private $db;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
    }

    public function isReady() {
        try {
            $this->db->query("SHOW TABLES LIKE 'employee_children'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function sexOptions() {
        return ['M' => 'Masculino', 'F' => 'Femenino'];
    }

    public static function sexLabel($sex) {
        $sex = strtoupper(trim((string)$sex));
        return self::sexOptions()[$sex] ?? '—';
    }

    /** @return array<int, object> */
    public function getByUserId($userId) {
        if (!$this->isReady()) {
            return [];
        }
        $this->db->query('SELECT id, user_id, birth_date, sex, sort_order FROM employee_children WHERE user_id = ? ORDER BY sort_order ASC, id ASC');
        return $this->db->resultSet([(int)$userId]);
    }

    public static function rowsFromPost(array $post) {
        $rows = [];
        if (empty($post['children_rows']) || !is_array($post['children_rows'])) {
            return $rows;
        }
        foreach ($post['children_rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $birth = trim((string)($row['birth_date'] ?? ''));
            $sex = strtoupper(trim((string)($row['sex'] ?? '')));
            if ($birth === '' && $sex === '') {
                continue;
            }
            $rows[] = ['birth_date' => $birth, 'sex' => $sex];
        }
        return $rows;
    }

    public static function validateRows(array $rows, $hasChildren) {
        $errors = [];
        $hasChildren = !empty($hasChildren);
        if ($hasChildren && $rows === []) {
            $errors['children'] = 'Agregá al menos un hijo/a o desmarcá «Tengo hijos/as».';
            return $errors;
        }
        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $birth = trim((string)($row['birth_date'] ?? ''));
            $sex = strtoupper(trim((string)($row['sex'] ?? '')));
            if ($birth === '') {
                $errors['children_' . $n] = 'Falta la fecha de nacimiento del hijo/a #' . $n . '.';
                continue;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
                $errors['children_' . $n] = 'Fecha inválida en hijo/a #' . $n . '.';
                continue;
            }
            if ($birth > date('Y-m-d')) {
                $errors['children_' . $n] = 'La fecha de nacimiento del hijo/a #' . $n . ' no puede ser futura.';
                continue;
            }
            if (!isset(self::sexOptions()[$sex])) {
                $errors['children_' . $n] = 'Seleccioná el sexo del hijo/a #' . $n . '.';
            }
        }
        return $errors;
    }

    public function saveForUser($userId, array $rows) {
        if (!$this->isReady()) {
            return true;
        }
        $userId = (int)$userId;
        $this->db->beginTransaction();
        try {
            $this->db->query('DELETE FROM employee_children WHERE user_id = ?');
            $this->db->execute([$userId]);
            $sort = 0;
            foreach ($rows as $row) {
                $birth = trim((string)($row['birth_date'] ?? ''));
                $sex = strtoupper(trim((string)($row['sex'] ?? '')));
                if ($birth === '' || !isset(self::sexOptions()[$sex])) {
                    continue;
                }
                $this->db->query('INSERT INTO employee_children (user_id, birth_date, sex, sort_order) VALUES (?, ?, ?, ?)');
                $this->db->execute([$userId, $birth, $sex, $sort]);
                $sort++;
            }
            $this->syncChildrenCount($userId, $sort);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function syncChildrenCount($userId, $count) {
        $userModel = new User($this->db);
        if (!$userModel->isPersonalFileReady()) {
            return;
        }
        $this->db->query('UPDATE users SET children_count = ? WHERE id = ?');
        $this->db->execute([$count > 0 ? $count : null, (int)$userId]);
    }
}
