-- 1. User's table
CREATE TABLE Users(
    UserID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for User
    Name VARCHAR(100) NOT NULL,		-- User’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- User’s Email
    Phone VARCHAR(15),	-- User’s Phone number
    Role VARCHAR(100),	-- User’s Role(Admin, Front desk staff, Hosts)
    Password VARCHAR(255) NOT NULL	-- User’s Password
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
    HostID INT, 	-- Identifier for Host
    VisitorID INT,	-- Identifier for Visitor
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
-- 11. Notifications table
CREATE TABLE Notifications (
   NotificationID   INT AUTO_INCREMENT PRIMARY KEY,
   UserID           INT NOT NULL,            -- who gets alerted
   TicketID         INT NOT NULL,
   Type             ENUM('assignment','info_request') NOT NULL,
   Payload          JSON NOT NULL,           -- e.g. { "from":57, "message":"…" }
   IsRead           BOOLEAN DEFAULT FALSE,
   CreatedAt        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY (UserID)   REFERENCES Users(UserID),
   FOREIGN KEY (TicketID) REFERENCES Help_Desk(TicketID)
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

