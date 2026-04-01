-- Add unique constraint on label names per project
ALTER TABLE labels ADD UNIQUE KEY unique_label_per_project (project_id, name);
