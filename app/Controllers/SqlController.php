<?php
declare(strict_types=1);

/**
 * V15.3.0 — SQL Editor controller.
 * Trang /sql: trình duyệt/chỉnh sửa dữ liệu kiểu Navicat (SQLite + MariaDB).
 */
final class SqlController
{
    public function __construct(private AuthService $auth, private SqlQueryService $sql){}

    private function guard(): void
    {
        if (!$this->auth->check()) {
            tms_redirect('/login');
        }
    }

    private function json(mixed $data, int $code = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): void
    {
        $this->guard();
        $driver = $this->sql->getDriver();
        $list = $driver === 'sqlite' ? $this->sql->sqliteList() : $this->sql->mariaList();
        $error = null;
        try {
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        tms_view('sqleditor.index', [
            'driver' => $driver,
            'databases' => $list,
            'error' => $error,
            'csrf' => tms_csrf_token(),
        ]);
    }

    public function apiList(): void
    {
        $this->jsonApi();
        $driver = $this->sql->getDriver();
        $this->json(['driver' => $driver, 'databases' => $driver === 'sqlite' ? $this->sql->sqliteList() : $this->sql->mariaList()]);
    }

    public function apiQuery(): void
    {
        $this->jsonApi();
        $dbKey = (string)($_POST['db_key'] ?? '');
        $sql = (string)($_POST['sql'] ?? '');
        $readOnly = !empty($_POST['readonly']);
        try {
            $this->json($this->sql->query($dbKey, $sql, $readOnly));
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiTables(): void
    {
        $this->jsonApi();
        $dbKey = (string)($_GET['db_key'] ?? '');
        try {
            $this->json(['tables' => $this->sql->tables($dbKey)]);
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiStructure(): void
    {
        $this->jsonApi();
        $dbKey = (string)($_GET['db_key'] ?? '');
        $table = (string)($_GET['table'] ?? '');
        if ($table === '') {
            $this->json(['error' => 'Thiếu tên bảng.'], 400);
        }
        try {
            $this->json(['columns' => $this->sql->structure($dbKey, $table), 'primary_key' => $this->sql->primaryKey($dbKey, $table)]);
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiSaveCell(): void
    {
        $this->jsonApi();
        $dbKey = (string)($_POST['db_key'] ?? '');
        $table = (string)($_POST['table'] ?? '');
        $pkColumns = (array)($_POST['pk_columns'] ?? []);
        $pkValues = (array)($_POST['pk_values'] ?? []);
        $column = (string)($_POST['column'] ?? '');
        $value = (string)($_POST['value'] ?? '');
        if ($table === '' || $column === '') {
            $this->json(['error' => 'Thiếu bảng hoặc cột.'], 400);
        }
        try {
            $this->json(array_merge($this->sql->saveCell($dbKey, $table, $pkColumns, $pkValues, $column, $value), ['saved' => true]));
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiDeleteRow(): void
    {
        $this->jsonApi();
        $dbKey = (string)($_POST['db_key'] ?? '');
        $table = (string)($_POST['table'] ?? '');
        $pkColumns = (array)($_POST['pk_columns'] ?? []);
        $pkValues = (array)($_POST['pk_values'] ?? []);
        try {
            $this->json(array_merge($this->sql->deleteRow($dbKey, $table, $pkColumns, $pkValues), ['deleted' => true]));
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiInsertRow(): void
    {
        $this->jsonApi();
        $dbKey = (string)($_POST['db_key'] ?? '');
        $table = (string)($_POST['table'] ?? '');
        $values = (array)($_POST['values'] ?? []);
        try {
            $this->json(array_merge($this->sql->insertRow($dbKey, $table, $values), ['inserted' => true]));
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function jsonApi(): void
    {
        $this->guard();
        header('Content-Type: application/json; charset=utf-8');
        if (!tms_verify_csrf($_POST['csrf'] ?? $_GET['csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Phiên không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
