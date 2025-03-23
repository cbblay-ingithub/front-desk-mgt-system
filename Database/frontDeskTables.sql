-- 1. User's table
CREATE TABLE Users(
    UserID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for User
    Name VARCHAR(100) NOT NULL,		-- User’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- User’s Email
    Phone VARCHAR(15),	-- User’s Phone number
    Role VARCHAR(100),	-- User’s Role(Admin, Front desk staff, Hosts)
    Password VARCHAR(255) NOT NULL	-- User’s Password
);


-- Sample Data for User's table
INSERT INTO Users (Name, Email, Phone, Role, Password) VALUES
    ('Kofi Kuffour', 'kofi@example.com', '055-123-4567', 'Admin', '$2a$10$XdKrfuiDeSD5k8'),
    ('Sarah Mintah', 'sarah@example.com', '055-987-6543', 'Front Desk Staff', '$2a$10$ZfUSpRKxl4NK'),
    ('Michael Baffour', 'michael.baf@example.com', '055-234-5678', 'Host', '$2a$10$tAfPwdR4Zlbm'),
    ('Bob Zigger', 'zigger@example.com', '055-456-7890', 'Front Desk Staff', '$2a$10$lQvb2d')
;


-- 2. Visitor's table
CREATE TABLE Visitors(
    VisitorID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for Visitor
    Name VARCHAR(100) NOT NULL,		-- Visitor’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- Visitor’s Email
    Phone VARCHAR(15),	-- Visitor’s Phone number
    IDType VARCHAR(100),	-- The type of ID the Visitor presents.
    IDNumber VARCHAR(25)	-- Visitor’s ID number
);


-- Sample data for Visitor's table
INSERT INTO Visitors (Name, Email, Phone, IDType, IDNumber)VALUES
    ('Jane Fosu', 'jane.fosu@gmail.com', '055-111-2222', 'Student ID', '119708777'),
    ('Kwame Boateng', 'kwame.boat@gmail.com', '053-123-2962', 'Student ID', '119704701'),
    ('Abena Banson', 'banson657@gmail.com', '020-034-3932', 'Ghana Card', 'GHA719708777'),
   ('Suleman Adams', 'sule.adams@gmail.com', '055-067-2032', 'Ghana Card', 'GHA719708777'),
;


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


-- Sample Data for Visitor_Logs table
INSERT INTO Visitor_Logs (CheckInTime, CheckOutTime, HostID, VisitorID, Visit_Purpose) VALUES
    ('2025-03-15 09:30:00', '2025-03-15 10:45:00', 3, 1, 'Job Interview'),
    ('2025-03-16 13:15:00', '2025-03-16 14:30:00', 4, 2, 'Business Meeting'),
    ('2025-03-18 14:45:00', NULL, 4, 4, 'Product Demonstration'),
    ('2025-03-19 09:00:00', '2025-03-19 09:45:00', 3, 5, 'Consultation')
;


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


-- Sample Data for Appointments table
INSERT INTO Appointments (AppointmentTime, Status, HostID, VisitorID) VALUES
    ('2025-03-22 10:00:00', 'Upcoming', 3, 1),
    ('2025-03-20 14:30:00', 'Cancelled', 4, 2),
    ('2025-03-19 15:00:00', 'Ongoing', 4, 4),
    ('2025-03-25 09:30:00', 'Upcoming', 3, 5)
;


-- 5. Students' table
CREATE TABLE Students(
    StudentID INT PRIMARY KEY AUTO_INCREMENT, -- Identifier for Student
    Name VARCHAR(100) NOT NULL,		-- Student’s Full Name
    Email VARCHAR(100) NOT NULL UNIQUE,	-- Student’s Email
    Phone VARCHAR(15),	-- Student’s Phone number
    Department VARCHAR(100)	-- Student’s Department
);


-- Sample data for Student's table
INSERT INTO Students (Name, Email, Phone, Department) VALUES
    ('David Akoto', 'dakoto@st.ug.edu.gh', '055-066-5454', 'Computer Science'),
    ('Sophia Lee', 'sophia.lee@st.ug.edu.gh', '054-663-4834', 'Engineering'),
    ('James Wilson', 'james.w@st.ug.edu.gh', '023-888-9123', 'Business Administration')
;


-- 6. Ticket Categories table
CREATE TABLE TicketCategories ( 
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,  -- ID of Ticket Category
    CategoryName VARCHAR(50) NOT NULL UNIQUE, -- Name of the category
    Description TEXT, 	-- description of the category
    IsActive BOOLEAN DEFAULT TRUE, 
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP	-- time created 
);


-- Sample Data for TicketCategories table
INSERT INTO TicketCategories (CategoryName, Description) VALUES
    ('Hardware', 'Issues related to physical equipment'),
    ('Software', 'Issues related to applications and operating systems'),
    ('Network', 'Connectivity and internet access issues'),
    ('Access', 'Account access, permissions, and security issues')
;


--  7. Item Categories table
CREATE TABLE ItemCategories ( 
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,  -- ID of Item Category
    CategoryName VARCHAR(50) NOT NULL UNIQUE, -- Name of the category
    Description TEXT, 	-- description of the category
    IsActive BOOLEAN DEFAULT TRUE, 
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP	-- time created 
);


-- Sample Data for ItemCategories table
INSERT INTO ItemCategories (CategoryName, Description) VALUES
    ('Electronics', 'Phones, laptops, chargers, etc.'),
    ('Clothing', 'Jackets, hats, etc.'),
    ('Personal Items', 'Wallets, keys, ID cards, etc.'),
    ('Books/Documents', 'Textbooks, notebooks, etc.'),
    ('Accessories', 'Jewelry, watches, glasses, etc.')
;


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
    Status ENUM('lost', 'found', 'claimed', 'disposed') NOT NULL,	-Status of the item.
    PhotoPath VARCHAR(255),		-- paths to item images.
    ClaimedBy VARCHAR(100),	-- Name of claimant.
    ContactInfo VARCHAR(100),	-- Contact of claimant.
    IDProvided VARCHAR(50),		-- ID provided by the claimant.
    LocationStored VARCHAR(100),	-- Location the item is stored.
    FOREIGN KEY(ReportedBy) REFERENCES Users(UserID) ON DELETE RESTRICT,  
    FOREIGN KEY(SolvedBy) REFERENCES Users(UserID) ON DELETE SET NULL ,  
    FOREIGN KEY (CategoryID) REFERENCES ItemCategories (CategoryID)
);


-- Sample data for the lost_And_Found table
INSERT INTO Lost_And_Found (DateReported, ReportedBy, DateSolved, SolvedBy, Description, CategoryID, Location, Status, PhotoPath, ClaimedBy, ContactInfo, IDProvided, LocationStored) VALUES
    ('2025-03-10 13:25:00', 2, '2025-03-15 15:30:00', 1, 'Black Samsung smartphone', 1, 'Library', 'claimed', '/photos/items/phone1.jpg', 'James Wilson', '555-888-9999', 'Student ID S123456', 'Front Desk'),
    ('2025-03-12 09:15:00', 2, '2025-03-12 16:45:00', 5, 'Blue umbrella', 5, 'Cafeteria', 'claimed', '/photos/items/umbrella1.jpg', 'Maria Garcia', '555-333-4444', 'National ID N123789456', 'Storage Room'),
    ('2025-03-15 10:45:00', 5, NULL, NULL, 'Red notebook', 4, 'Classroom B12', 'found', '/photos/items/notebook1.jpg', NULL, NULL, NULL, 'Front Desk Drawer'),
    ('2025-03-16 14:20:00', 2, '2025-03-18 11:30:00', 1, 'Gray jacket', 2, 'Conference Room', 'disposed', '/photos/items/jacket1.jpg', NULL, NULL, NULL, 'Disposed')
;


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


-- Sample data for the Help desk table
INSERT INTO Help_Desk (CreatedBy, Description, AssignedTo, CategoryID, Status, Priority, CreatedDate, ResolutionNotes, ResolvedDate, TimeSpent) VALUES
    (3, 'Computer won''t turn on in Room B101', 1, 1, 'resolved', 'high', '2025-03-12 09:30:00', 'Power supply replaced', '2025-03-12 14:45:00', 315),
    (3, 'Printer in Room A200 is jammed', 5, 1, 'in-progress', 'medium', '2025-03-15 15:20:00', NULL, NULL, NULL),
    (5, 'WiFi not working in Conference Room', 1, 3, 'open', 'critical', '2025-03-17 10:45:00', NULL, NULL, NULL),
    (4, 'Need new chairs for waiting area', NULL, 5, 'pending', 'low', '2025-03-18 14:30:00', NULL, NULL, NULL)
;


-- 10. Student-Visitor Junction table
CREATE TABLE Student_Visitor ( 
    StudentID INT,	-- ID of the Student
    VisitorID INT,	-- ID of the Visitor
    PRIMARY KEY (StudentID, VisitorID), 
    FOREIGN KEY (StudentID) REFERENCES Students(StudentID) ON DELETE CASCADE, 
    FOREIGN KEY (VisitorID) REFERENCES Visitors(VisitorID) ON DELETE CASCADE
);

-- Sample Data for Student_Visitor Junction table
INSERT INTO Student_Visitor (StudentID, VisitorID) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5);

-- 11. Visitor-Items Junction table
CREATE TABLE Visitor_Items ( 
    VisitorID INT,		-- ID of the Student
    ItemID INT, 		-- ID of the Visitor
    PRIMARY KEY (VisitorID, ItemID), 
    FOREIGN KEY (VisitorID) REFERENCES Visitors(VisitorID) ON DELETE CASCADE, 
    FOREIGN KEY (ItemID) REFERENCES Lost_And_Found(ItemID) ON DELETE CASCADE
);

-- Sample Data for Visitor-Items Junction table
INSERT INTO Visitor_Items (VisitorID, ItemID) VALUES
(3, 3),
(4, 4),
(1, 1),
(2, 2),
(5, 5);

-- Some SQL queries showing functionality

-- I. This returns all users who are Hosts
SELECT Name, Email, Phone 
FROM Users 
WHERE Role = 'Host';

-- II. This returns all upcoming appointments
SELECT a.AppointmentTime, v.Name AS VisitorName, u.Name AS HostName
FROM Appointments a
JOIN Visitor v ON a.VisitorID = v.VisitorID
JOIN Users u ON a.HostID = u.UserID
WHERE Status = 'Upcoming';

-- III. This lists all items that have been found but not claimed
SELECT Description, Location, DateReported
FROM Lost_And_Found
WHERE Status = 'found';

-- IV. This lists all visitors who have reported or claimed items
SELECT DISTINCT v.VisitorID, v.Name, v.Email, v.Phone
FROM Visitor v
JOIN Visitor_Items vi ON v.VisitorID = vi.VisitorID;