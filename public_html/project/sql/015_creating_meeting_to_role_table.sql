CREATE TABLE IF NOT EXISTS meeting_roles (
    meeting_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (meeting_id, role_id),
    FOREIGN KEY (meeting_id) REFERENCES Meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES Roles(id) ON DELETE CASCADE,
    `created`    timestamp default current_timestamp,
    `modified`   timestamp default current_timestamp on update current_timestamp
);
