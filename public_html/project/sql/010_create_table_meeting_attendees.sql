CREATE TABLE IF NOT EXISTS meeting_attendees (
    meeting_id INT,
    attendee_id INT,
    PRIMARY KEY (meeting_id, attendee_id),
    FOREIGN KEY (meeting_id) REFERENCES Meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (attendee_id) REFERENCES Users(id) ON DELETE CASCADE
);