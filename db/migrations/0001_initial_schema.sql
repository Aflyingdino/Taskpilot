CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    project_id   INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) NOT NULL,
    description  TEXT,
    main_color   VARCHAR(7)  DEFAULT '#5b5bd6',
    accent_color VARCHAR(7),
    public_token VARCHAR(64) DEFAULT NULL,
    archived_at  DATETIME    DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_members (
    project_id INT NOT NULL,
    user_id    INT NOT NULL,
    role       ENUM('owner','admin','collaborator') NOT NULL,
    PRIMARY KEY (project_id, user_id),
    CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS board_groups (
    group_id     INT AUTO_INCREMENT PRIMARY KEY,
    project_id   INT NOT NULL,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    status       ENUM('not_started','started','ready_for_test','done') NOT NULL DEFAULT 'not_started',
    priority     ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    deadline     DATE        DEFAULT NULL,
    main_color   VARCHAR(7)  DEFAULT NULL,
    accent_color VARCHAR(7)  DEFAULT NULL,
    grid_row     INT NOT NULL DEFAULT 0,
    grid_col     INT NOT NULL DEFAULT 0,
    position     INT NOT NULL DEFAULT 0,
    archived_at  DATETIME    DEFAULT NULL,
    CONSTRAINT fk_bg_project FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    task_id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NOT NULL,
    group_id         INT DEFAULT NULL,
    title            VARCHAR(150) NOT NULL,
    description      TEXT,
    status           ENUM('not_started','started','ready_for_test','done') NOT NULL DEFAULT 'not_started',
    priority         ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    due_date         DATE     DEFAULT NULL,
    scheduled_start  DATETIME DEFAULT NULL,
    duration_minutes INT      DEFAULT NULL,
    calendar_color   VARCHAR(7) DEFAULT NULL,
    main_color       VARCHAR(7) DEFAULT NULL,
    accent_color     VARCHAR(7) DEFAULT NULL,
    position         INT NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_group   FOREIGN KEY (group_id)   REFERENCES board_groups(group_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_assignees (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (task_id, user_id),
    CONSTRAINT fk_ta_task FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS labels (
    label_id   INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name       VARCHAR(50) NOT NULL,
    color      VARCHAR(7)  DEFAULT '#5b5bd6',
    CONSTRAINT fk_labels_project FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_labels (
    task_id  INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (task_id, label_id),
    CONSTRAINT fk_tl_task  FOREIGN KEY (task_id)  REFERENCES tasks(task_id)   ON DELETE CASCADE,
    CONSTRAINT fk_tl_label FOREIGN KEY (label_id) REFERENCES labels(label_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_labels (
    group_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (group_id, label_id),
    CONSTRAINT fk_gl_group FOREIGN KEY (group_id) REFERENCES board_groups(group_id) ON DELETE CASCADE,
    CONSTRAINT fk_gl_label FOREIGN KEY (label_id) REFERENCES labels(label_id)       ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT NOT NULL,
    user_id    INT NOT NULL,
    body       TEXT NOT NULL,
    is_pinned  TINYINT(1) NOT NULL DEFAULT 0,
    edited_at  DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_notes (
    note_id      INT AUTO_INCREMENT PRIMARY KEY,
    task_id      INT NOT NULL,
    user_id      INT NOT NULL,
    title        VARCHAR(150) NOT NULL DEFAULT 'Note',
    content      TEXT,
    content_type ENUM('text','image','video') NOT NULL DEFAULT 'text',
    bg_color     VARCHAR(7) DEFAULT '#5b5bd6',
    text_color   VARCHAR(7) DEFAULT '#ffffff',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notes_task FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    user_id     INT DEFAULT NULL,
    type        VARCHAR(50) NOT NULL,
    message     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id       INT DEFAULT NULL,
    uploaded_by   INT NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    url           VARCHAR(512) NOT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attach_task FOREIGN KEY (task_id)     REFERENCES tasks(task_id) ON DELETE SET NULL,
    CONSTRAINT fk_attach_user FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
