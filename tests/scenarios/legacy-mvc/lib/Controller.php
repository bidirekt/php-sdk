<?php

abstract class Controller
{
    /** @var PDO */
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param mixed $data
     * @param int $status
     */
    protected function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * @param int $status
     */
    protected function status($status)
    {
        http_response_code($status);
    }

    /**
     * @param string $view
     * @param array $data
     */
    protected function render($view, $data = array())
    {
        extract($data);
        include __DIR__ . '/../views/' . $view . '.php';
    }

    /**
     * @return array
     */
    protected function input()
    {
        return json_decode(file_get_contents('php://input'), true) ?: array();
    }
}
