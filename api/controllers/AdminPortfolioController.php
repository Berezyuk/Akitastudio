<?php
// api/controllers/AdminPortfolioController.php — портфолио
require_once __DIR__ . '/../config/admin_deps.php';

class AdminPortfolioController {

    private static function checkAdmin() {
        requireAdmin();
    }

    public static function getPortfolio() {
        self::checkAdmin();
        $portfolio = new Portfolio();
        $items = $portfolio->getAll();
        echo json_encode(['success' => true, 'portfolio' => $items]);
    }

    public static function addPortfolio() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $portfolio = new Portfolio();
        if (!empty($data['show_on_home']) && $portfolio->countHomeItems() >= 8) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $portfolio->create($data);
        echo json_encode($result);
    }

    public static function updatePortfolio($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $portfolio = new Portfolio();
        if (!empty($data['show_on_home']) && $portfolio->countHomeItems($id) >= 8) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $portfolio->update($id, $data);
        echo json_encode($result);
    }

    public static function deletePortfolio($id) {
        self::checkAdmin();
        $portfolio = new Portfolio();
        $result = $portfolio->delete($id);
        echo json_encode($result);
    }

    // POST /api/admin/portfolio/upload  (multipart, поле 'media')
    // Возвращает { success: true, url: '...' } — URL вставляется в поле video_url формы.
    public static function uploadPortfolioMedia() {
        requireRole('admin');

        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Ошибка загрузки файла']);
            return;
        }

        $file = $_FILES['media'];

        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/ogg',
        ];
        $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($realMime, $allowedTypes)) {
            echo json_encode(['error' => 'Разрешены: JPG, PNG, WEBP, GIF, MP4, WEBM, OGG']);
            return;
        }

        if ($file['size'] > 100 * 1024 * 1024) {
            echo json_encode(['error' => 'Файл слишком большой. Максимум 100 МБ']);
            return;
        }

        $uploadPath = $file['tmp_name'];
        $uploadMime = $realMime;
        $transcodedPath = null;

        if (str_starts_with($realMime, 'video/')) {
            $transcodedPath = FfmpegHelper::transcodeToH264($file['tmp_name'], $realMime);
            if ($transcodedPath) {
                $uploadPath = $transcodedPath;
                $uploadMime = 'video/mp4';
            }
        }

        $key = MinioHelper::generateKey('media', $transcodedPath ? 'video.mp4' : $file['name']);

        try {
            $url = MinioHelper::upload('portfolio', $key, $uploadPath, $uploadMime);
        } catch (Exception $e) {
            error_log('MinIO portfolio upload error: ' . $e->getMessage());
            if ($transcodedPath && file_exists($transcodedPath)) unlink($transcodedPath);
            echo json_encode(['error' => 'Не удалось загрузить файл в хранилище']);
            return;
        } finally {
            if ($transcodedPath && file_exists($transcodedPath)) unlink($transcodedPath);
        }

        echo json_encode(['success' => true, 'url' => $url]);
    }
}
