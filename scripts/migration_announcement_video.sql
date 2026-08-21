-- Avisos emergentes: soporte de video (mp4/webm) además de imagen.
-- El archivo se guarda en public/uploads/announcements y se sirve por
-- streaming autenticado (employee/streamAnnouncementVideo).
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS video_path VARCHAR(255) NULL DEFAULT NULL AFTER image_path;
