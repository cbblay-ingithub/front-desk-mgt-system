-- 1. User's table
CREATE TABLE Users(
    UserID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for User
    Name VARCHAR(100) NOT NULL,		-- User’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- User’s Email
    Phone VARCHAR(15),	-- User’s Phone number
    Role VARCHAR(100),	-- User’s Role(Admin, Front desk staff, Hosts)
    Password VARCHAR(255) NOT NULL,	-- User’s Password
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_activity DATETIME,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- 2. Visitor's table
CREATE TABLE Visitors(
    VisitorID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for Visitor
    Name VARCHAR(100) NOT NULL,		-- Visitor’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- Visitor’s Email
    Phone VARCHAR(15),	-- Visitor’s Phone number
    IDType VARCHAR(100),	-- The type of ID the Visitor presents.
    IDNumber VARCHAR(25),	-- Visitor’s ID number
    Status VARCHAR(25),     -- Status of Visit
    Visit_Purpose VARCHAR(100) NOT NULL 	-- Purpose of Visit
);


-- 3. Visitor Logs table
CREATE TABLE Visitor_Logs(
    LogID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for Visitor log
    CheckInTime DATETIME NOT NULL,	-- Check-In time of a visitor
    CheckOutTime DATETIME,	-- Check-Out time of a visitor
    HostID INT, 	-- Identifier for Host
    VisitorID INT,	-- Identifier for Visitor
    Visit_Purpose VARCHAR(100),	-- Purpose of Visit
    FOREIGN KEY(VisitorID) REFERENCES Visitors(VisitorID) ON DELETE CASCADE,
    FOREIGN KEY(HostID) REFERENCES Users(UserID) ON DELETE SET NULL
);


-- 4. Appointments
CREATE TABLE Appointments(
    AppointmentID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for Appointments
    AppointmentTime DATETIME NOT NULL	, -- Appointment time 
    Status ENUM('Cancelled', 'Ongoing', 'Upcoming') NOT NULL,	-- Appointment Status.
    CheckInTime DATETIME NULL, -- Check-in Time
    HostID INT, 	-- Identifier for Host
    VisitorID INT,	-- Identifier for Visitor
    Purpose varchar(255) DEFAULT NULL,
    SessionEndTime datetime DEFAULT NULL,
    CancellationReason enum('No-Show','Visitor Cancelled','Host Cancelled','Scheduling Conflict','Emergency','Other') NOT NULL,
    ScheduledBy int DEFAULT NULL,
    BadgeNumber varchar(20) DEFAULT NULL,
    IsCheckedIn tinyint(1) DEFAULT '0',
    FOREIGN KEY(VisitorID) REFERENCES Visitors(VisitorID) ON DELETE CASCADE,
    FOREIGN KEY(HostID) REFERENCES Users(UserID) ON DELETE SET NULL
);


-- 5. Students' table
CREATE TABLE Students(
    StudentID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for Student
    Name VARCHAR(100) NOT NULL,		-- Student’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- Student’s Email
    Phone VARCHAR(15),	-- Student’s Phone number
    Department VARCHAR(100)	-- Student’s Department
);


-- 6. Ticket Categories table
CREATE TABLE TicketCategories ( 
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,  -- ID of Ticket Category
    CategoryName VARCHAR(50) NOT NULL UNIQUE, -- Name of the category
    Description TEXT, 	-- description of the category
    IsActive BOOLEAN DEFAULT TRUE, 
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP	-- time created 
);

--  7. Item Categories table
CREATE TABLE ItemCategories ( 
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,  -- ID of Item Category
    CategoryName VARCHAR(50) NOT NULL UNIQUE, -- Name of the category
    Description TEXT, 	-- description of the category
    IsActive BOOLEAN DEFAULT TRUE, 
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP	-- time created 
);

-- 8. Lost & found table
CREATE TABLE Lost_And_Found(
    ItemID INT PRIMARY KEY AUTO_INCREMENT,-- Identifier for Item.
    DateReported DATETIME NOT NULL,	-- Report date.
    ReportedBy INT NOT NULL,   	-- ID of the User who reported it.
    DateSolved DATETIME NULL,	-- Date solved.
    SolvedBy INT NULL,   -- ID of the User who resolved it.
    Description VARCHAR(255) NOT NULL,  -- Description of the item
    CategoryID INT,	-- Category of the item.
    Location  VARCHAR(100) NOT NULL,	-- location the item was found.
    Status ENUM('lost', 'found', 'claimed', 'disposed') NOT NULL,	-- Status of the item.
    PhotoPath VARCHAR(255),		-- paths to item images.
    ClaimedBy VARCHAR(100),	-- Name of claimant.
    ContactInfo VARCHAR(100),	-- Contact of claimant.
    IDProvided VARCHAR(50),		-- ID provided by the claimant.
    LocationStored VARCHAR(100),	-- Location the item is stored.
    FOREIGN KEY(ReportedBy) REFERENCES Users(UserID) ON DELETE RESTRICT,  
    FOREIGN KEY(SolvedBy) REFERENCES Users(UserID) ON DELETE SET NULL ,  
    FOREIGN KEY (CategoryID) REFERENCES ItemCategories (CategoryID)
);


-- 9. Help Desk table
CREATE TABLE Help_Desk(
    TicketID INT PRIMARY KEY AUTO_INCREMENT,	-- Identifier for Ticket.
    CreatedBy INT NOT NULL,  -- ID of the User who created the Ticket.
    Description TEXT NOT NULL, -- Description of the problem
    AssignedTo INT,	-- ID of the User assigned to address the problem.
    CategoryID INT,	-- Category of the problem
    Status ENUM('open', 'in-progress', 'pending', 'resolved', 'closed') NOT NULL DEFAULT 'open', 
    Priority ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    CreatedDate DATETIME NOT NULL, 	-- Time the Ticket was created.
    ResolutionNotes TEXT,	-- Details how the issue was resolved.
    ResolvedDate DATETIME,	-- Time the problem is addressed.
    TimeSpent INT,	-- spent in addressing the issue.
    FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE RESTRICT,
    FOREIGN KEY (AssignedTo) REFERENCES Users(UserID) ON DELETE SET NULL,
    FOREIGN KEY (CategoryID) REFERENCES TicketCategories (CategoryID)
);
-- 10. Notifications table
CREATE TABLE notifications (
   id INT NOT NULL AUTO_INCREMENT,
   user_id INT NOT NULL, -- The admin user ID who should see this notification
   type VARCHAR(50) NOT NULL, -- e.g., 'password_reset_request'
   title VARCHAR(255) NOT NULL,
   message TEXT NOT NULL,
   related_entity_type VARCHAR(50), -- e.g., 'user'
   related_entity_id INT, -- e.g., the user ID who requested the reset
   is_read TINYINT(1) DEFAULT 0,
   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (id),
   KEY user_id (user_id),
   KEY is_read (is_read),
   CONSTRAINT fk_notifications_user_id FOREIGN KEY (user_id) REFERENCES users (UserID) ON DELETE CASCADE
);


-- 11. Student-Visitor Junction table
CREATE TABLE Student_Visitor ( 
    StudentID INT,	-- ID of the Student
    VisitorID INT,	-- ID of the Visitor
    PRIMARY KEY (StudentID, VisitorID), 
    FOREIGN KEY (StudentID) REFERENCES Students(StudentID) ON DELETE CASCADE, 
    FOREIGN KEY (VisitorID) REFERENCES Visitors(VisitorID) ON DELETE CASCADE
);

-- 12. Visitor-Items Junction table
CREATE TABLE Visitor_Items ( 
    VisitorID INT,		-- ID of the Student
    ItemID INT, 		-- ID of the Visitor
    PRIMARY KEY (VisitorID, ItemID), 
    FOREIGN KEY (VisitorID) REFERENCES Visitors(VisitorID) ON DELETE CASCADE, 
    FOREIGN KEY (ItemID) REFERENCES Lost_And_Found(ItemID) ON DELETE CASCADE
);

-- 13. user_activity_log table
CREATE TABLE user_activity_log (
   id INT AUTO_INCREMENT PRIMARY KEY,
   user_id INT,
   activity VARCHAR(255),
   activity_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY (user_id) REFERENCES users(UserID)
);

-- 14. audit_logs table
CREATE TABLE audit_logs
(
    log_id          CHAR(8) PRIMARY KEY,
    user_id         INT                         NOT NULL,
    user_role       VARCHAR(20)                 NOT NULL, -- admin, front_desk, host, support
    action_type     VARCHAR(50)                 NOT NULL,
    action_category VARCHAR(30)                 NOT NULL,
    table_affected  VARCHAR(50),
    record_id       INT,
    old_value       JSON,
    new_value       JSON,
    ip_address      VARCHAR(45)                 NOT NULL,
    user_agent      VARCHAR(255),
    session_id      VARCHAR(64),
    status          ENUM ('SUCCESS', 'FAILURE') NOT NULL DEFAULT 'SUCCESS',
    description     VARCHAR(500),                         -- Human-readable description
    created_at      TIMESTAMP                            DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(UserID)
);
-- 15. password_policy table
CREATE TABLE `password_policy` (
   `id` CHAR(6) NOT NULL PRIMARY KEY,
    -- Settings for USER-CHOSEN permanent passwords
   `min_length` INT NOT NULL DEFAULT 8,
   `require_uppercase` TINYINT NOT NULL DEFAULT 1,
   `require_lowercase` TINYINT NOT NULL DEFAULT 1,
   `require_numbers` TINYINT NOT NULL DEFAULT 1,
   `require_special_chars` TINYINT NOT NULL DEFAULT 0,

    -- Settings for SYSTEM-GENERATED temporary passwords
   `temp_password_length` INT NOT NULL DEFAULT 12,
   `temp_password_expiry_hours` INT NOT NULL DEFAULT 24,

   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 16. password_reset_log table
CREATE TABLE `password_reset_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `request_type` enum('user_requested','admin_forced') NOT NULL,
  `requested_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','completed','expired') NOT NULL DEFAULT 'pending',
  `handled_by_admin_id` int DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `handled_by_admin_id` (`handled_by_admin_id`),
  CONSTRAINT `password_reset_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  CONSTRAINT `password_reset_log_ibfk_2` FOREIGN KEY (`handled_by_admin_id`) REFERENCES `users` (`UserID`) ON DELETE SET NULL
);

-- 17. badge_print_logs
CREATE TABLE IF NOT EXISTS badge_print_logs (
    LogID INT PRIMARY KEY AUTO_INCREMENT,
    VisitorID INT NOT NULL,
    BadgeNumber VARCHAR(20) NOT NULL,
    Copies INT DEFAULT 1,
    PrintedBy INT NOT NULL,
    PrintTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraints
    FOREIGN KEY (VisitorID) REFERENCES visitors(VisitorID) ON DELETE CASCADE,
    FOREIGN KEY (PrintedBy) REFERENCES users(UserID) ON DELETE CASCADE,

    -- Index for performance
    INDEX idx_visitor_badge (VisitorID, BadgeNumber),
    INDEX idx_print_time (PrintTime),
    INDEX idx_printed_by (PrintedBy)
);

