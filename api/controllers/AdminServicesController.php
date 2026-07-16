<?php
// api/controllers/AdminServicesController.php — услуги и категории услуг
require_once __DIR__ . '/../config/admin_deps.php';

class AdminServicesController {

    private static function checkAdmin() {
        requireAdmin();
    }

    // ========== УСЛУГИ ==========
    public static function getServices() {
        self::checkAdmin();
        $service = new Service();
        $services = $service->getAll();
        echo json_encode(['success' => true, 'services' => $services]);
    }

    public static function addService() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $service = new Service();
        $result = $service->create($data);
        echo json_encode($result);
    }

    public static function updateService($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $service = new Service();
        $result = $service->update($id, $data);
        echo json_encode($result);
    }

    public static function deleteService($id) {
        self::checkAdmin();
        $service = new Service();
        $result = $service->delete($id);
        echo json_encode($result);
    }

    public static function getServicesByCategory($categoryId) {
        self::checkAdmin();
        $service = new Service();
        $services = $service->getByCategory($categoryId);
        echo json_encode(['success' => true, 'services' => $services]);
    }

    // ========== КАТЕГОРИИ УСЛУГ ==========
    public static function getServiceCategories() {
        self::checkAdmin();
        $cat = new ServiceCategory();
        $categories = $cat->getAll();
        echo json_encode(['success' => true, 'categories' => $categories]);
    }

    public static function addServiceCategory() {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $cat = new ServiceCategory();
        if (!empty($data['show_on_home']) && $cat->countHomeItems() >= 8) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $cat->create(
            $data['name'],
            $data['sort_order'],
            $data['icon'] ?? '',
            !empty($data['show_on_home'])
        );
        echo json_encode($result);
    }

    public static function updateServiceCategory($id) {
        self::checkAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $cat = new ServiceCategory();
        if (!empty($data['show_on_home']) && $cat->countHomeItems($id) >= 8) {
            echo json_encode(['success' => false, 'home_limit_exceeded' => true]);
            return;
        }
        $result = $cat->update(
            $id,
            $data['name'],
            $data['sort_order'],
            $data['icon'] ?? '',
            !empty($data['show_on_home'])
        );
        echo json_encode($result);
    }

    public static function deleteServiceCategory($id) {
        self::checkAdmin();
        $cat = new ServiceCategory();
        // Удаляем медиа из MinIO если есть
        $existing = $cat->getMediaUrl($id);
        if ($existing) {
            $parsed = MinioHelper::parseUrl($existing);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (\Exception $e) {}
            }
        }
        $result = $cat->delete($id);
        echo json_encode($result);
    }

    // POST /api/admin/service-categories/:id/media  (multipart, поле 'media')
    public static function uploadCategoryMedia($id) {
        self::checkAdmin();

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
            echo json_encode(['error' => 'Максимум 100 МБ']);
            return;
        }

        $cat = new ServiceCategory();

        // Удаляем старое медиа
        $existing = $cat->getMediaUrl($id);
        if ($existing) {
            $parsed = MinioHelper::parseUrl($existing);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (\Exception $e) {}
            }
        }

        $uploadPath = $file['tmp_name'];
        $uploadMime = $realMime;
        $transcodedPath = null;

        if (str_starts_with($realMime, 'video/')) {
            $transcodedPath = FfmpegHelper::transcodeToH264($file['tmp_name'], $realMime);
            if ($transcodedPath) {
                $uploadPath = $transcodedPath;
                $uploadMime = 'video/mp4';
            } else {
                // ffmpeg не смог обработать файл — заливать необработанный оригинал
                // нельзя (любой вес, звук, не готов для мобильного канала).
                echo json_encode(['error' => 'Не удалось обработать видео. Попробуйте другой файл']);
                return;
            }
        }

        $key = MinioHelper::generateKey('category-media', $transcodedPath ? 'video.mp4' : $file['name']);
        try {
            $url = MinioHelper::upload('portfolio', $key, $uploadPath, $uploadMime);
        } catch (\Exception $e) {
            error_log('MinIO category media upload error: ' . $e->getMessage());
            if ($transcodedPath && file_exists($transcodedPath)) unlink($transcodedPath);
            echo json_encode(['error' => 'Не удалось загрузить файл']);
            return;
        } finally {
            if ($transcodedPath && file_exists($transcodedPath)) unlink($transcodedPath);
        }

        $cat->updateMedia($id, $url);
        echo json_encode(['success' => true, 'url' => $url]);
    }

    // DELETE /api/admin/service-categories/:id/media
    public static function deleteCategoryMedia($id) {
        self::checkAdmin();
        $cat = new ServiceCategory();
        $existing = $cat->getMediaUrl($id);
        if ($existing) {
            $parsed = MinioHelper::parseUrl($existing);
            if ($parsed) {
                try { MinioHelper::delete($parsed['bucket'], $parsed['key']); } catch (\Exception $e) {}
            }
        }
        $cat->updateMedia($id, null);
        echo json_encode(['success' => true]);
    }
}
