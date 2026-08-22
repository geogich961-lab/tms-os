<?php
declare(strict_types=1);

final class MarketplaceController
{
    public function __construct(private AppInstallerService $installer) {}

    public function index(): void
    {
        $catalog = $this->installer->catalog();
        $installed = $this->installer->installed();
        require __DIR__ . '/../Views/marketplace/index.php';
    }

    public function install(): void
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $result = $this->installer->install($input);
            echo json_encode($result);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
