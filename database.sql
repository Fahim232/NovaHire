-- NovaHire - Complete Database
-- Merged from: database.sql, company_database.sql, add_category_applications.sql,
--               interviews.sql, message_alerts.sql, password_reset.sql, upgrade_v2.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- ADMIN LOGIN
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_login` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_user_name` varchar(255) NOT NULL,
  `admin_password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default password is 'admin123' (bcrypt-hashed)
INSERT INTO `admin_login` (`admin_user_name`, `admin_password`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- USER REGISTRATION (legacy)
-- ============================================================
CREATE TABLE IF NOT EXISTS `jobregistration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `degree` varchar(255) NOT NULL,
  `refer` varchar(255) DEFAULT NULL,
  `planguage` varchar(255) NOT NULL,
  `cv_doc` varchar(255) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USER INFO (main user accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `about_me` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `cpassword` varchar(255) NOT NULL,
  `user_degree` varchar(255) NOT NULL,
  `user_skills` varchar(255) NOT NULL,
  `profile` varchar(255) DEFAULT NULL,
  `auto_cv_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PASSWORD RESET
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_reset_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `user_type` enum('user','company') NOT NULL DEFAULT 'user',
  `reset_code` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `reset_code` (`reset_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- QUIZ QUESTIONS (category-based grooming quizzes)
-- ============================================================
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `question` text NOT NULL,
  `option1` varchar(255) NOT NULL,
  `option2` varchar(255) NOT NULL,
  `option3` varchar(255) NOT NULL,
  `option4` varchar(255) NOT NULL,
  `answer` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PHP Questions
INSERT INTO `quiz_questions` (`category`, `question`, `option1`, `option2`, `option3`, `option4`, `answer`) VALUES
('PHP', 'What does PHP stand for?', 'Personal Home Page', 'PHP: Hypertext Preprocessor', 'Private Home Page', 'Personal Hypertext Processor', 'PHP: Hypertext Preprocessor'),
('PHP', 'Which symbol is used to access properties of an object in PHP?', '.', '->', '::', '@', '->'),
('PHP', 'Which function is used to include a file and stop execution if not found?', 'include()', 'require()', 'load()', 'attach()', 'require()'),
('PHP', 'Which operator is used to concatenate strings in PHP?', '+', '.', '&&', '&', '.'),
('PHP', 'Which superglobal contains form data sent via GET?', '$_POST', '$_COOKIE', '$_GET', '$_SESSION', '$_GET'),
('PHP', 'Which function starts a session in PHP?', 'start_session()', 'session_begin()', 'session_start()', 'begin_session()', 'session_start()'),
('PHP', 'Which built-in function escapes HTML characters to prevent XSS?', 'htmlspecialchars()', 'strip_tags()', 'addslashes()', 'urlencode()', 'htmlspecialchars()');

-- Java Questions
INSERT INTO `quiz_questions` (`category`, `question`, `option1`, `option2`, `option3`, `option4`, `answer`) VALUES
('Java', 'Which of the following is not a Java features?', 'Dynamic', 'Architecture Neutral', 'Use of pointers', 'Object-oriented', 'Use of pointers'),
('Java', 'What is the return type of the hashCode() method in the Object class?', 'Object', 'int', 'long', 'void', 'int'),
('Java', 'Which keyword is used for inheritance in Java?', 'implements', 'extends', 'inherits', 'instanceof', 'extends'),
('Java', 'What does JVM stand for?', 'Java Variable Machine', 'Java Vendor Machine', 'Java Virtual Machine', 'Java Verified Machine', 'Java Virtual Machine'),
('Java', 'Checked exceptions are subclasses of which class?', 'RuntimeException', 'Error', 'Exception', 'Throwable', 'Exception'),
('Java', 'What is the default value of a boolean variable in a Java class?', 'true', 'false', '0', 'null', 'false');

-- Python Questions
INSERT INTO `quiz_questions` (`category`, `question`, `option1`, `option2`, `option3`, `option4`, `answer`) VALUES
('Python', 'Which data type is used to store multiple items in a single variable?', 'List', 'Integer', 'Float', 'Boolean', 'List'),
('Python', 'How do you create a variable with the numeric value 5?', 'x = 5', 'x = int(5)', 'Both are correct', 'None is correct', 'Both are correct'),
('Python', 'Which keyword is used to define a function in Python?', 'func', 'def', 'function', 'lambda', 'def'),
('Python', 'Which collection type is immutable?', 'List', 'Tuple', 'Set', 'Dictionary', 'Tuple'),
('Python', 'Which command installs a package named requests?', 'pip install requests', 'pip get requests', 'python -m requests', 'pip add requests', 'pip install requests'),
('Python', 'Which library provides the DataFrame structure?', 'numpy', 'pandas', 'requests', 'matplotlib', 'pandas');

-- Frontend Questions
INSERT INTO `quiz_questions` (`category`, `question`, `option1`, `option2`, `option3`, `option4`, `answer`) VALUES
('Frontend', 'Which HTML tag is used to define an internal style sheet?', '<css>', '<script>', '<style>', '<link>', '<style>'),
('Frontend', 'Which CSS property is used to change the text color?', 'color', 'text-color', 'font-color', 'text-style', 'color'),
('Frontend', 'Which HTML attribute is used to define inline styles?', 'class', 'style', 'styles', 'font', 'style'),
('Frontend', 'What does CSS stand for?', 'Cascading Style Sheets', 'Creative Style Sheets', 'Computer Style Sheets', 'Colorful Style Sheets', 'Cascading Style Sheets'),
('Frontend', 'Which property is used to change the background color in CSS?', 'bgcolor', 'background-color', 'color', 'bg-color', 'background-color'),
('Frontend', 'How do you select an element with id "demo" in CSS?', '.demo', '#demo', '*demo', 'demo', '#demo'),
('Frontend', 'Which JavaScript method is used to select an element by ID?', 'querySelector()', 'getElementById()', 'getElement()', 'selectById()', 'getElementById()'),
('Frontend', 'What is the correct HTML for creating a hyperlink?', '<a href="url">Link</a>', '<link>url</link>', '<a url="link">Text</a>', '<hyperlink>url</hyperlink>', '<a href="url">Link</a>'),
('Frontend', 'Which CSS property controls the text size?', 'text-size', 'font-size', 'text-style', 'font-style', 'font-size'),
('Frontend', 'How do you declare a JavaScript variable?', 'variable name;', 'v name;', 'var name;', 'declare name;', 'var name;'),
('Frontend', 'Which event occurs when a user clicks on an HTML element?', 'onchange', 'onmouseover', 'onclick', 'onhover', 'onclick'),
('Frontend', 'What is the correct syntax for referring to an external JavaScript file?', '<script href="file.js">', '<script name="file.js">', '<script src="file.js">', '<javascript>file.js</javascript>', '<script src="file.js">'),
('Frontend', 'Which HTML tag is used to define a JavaScript?', '<javascript>', '<js>', '<script>', '<code>', '<script>'),
('Frontend', 'How do you create a function in JavaScript?', 'function myFunction()', 'function:myFunction()', 'create myFunction()', 'def myFunction()', 'function myFunction()'),
('Frontend', 'Which CSS property is used to make text bold?', 'text-weight', 'font-weight', 'text-style', 'font-bold', 'font-weight'),
('Frontend', 'What is the correct HTML for inserting an image?', '<img href="image.jpg">', '<image src="image.jpg">', '<img src="image.jpg">', '<picture>image.jpg</picture>', '<img src="image.jpg">'),
('Frontend', 'Which CSS display value is used to create a flexible container?', 'flex', 'flexbox', 'flexible', 'grid', 'flex'),
('Frontend', 'What does DOM stand for?', 'Document Object Model', 'Display Object Management', 'Digital Orientation Model', 'Document Oriented Model', 'Document Object Model'),
('Frontend', 'Which HTML5 element is used for playing video files?', '<movie>', '<media>', '<video>', '<film>', '<video>'),
('Frontend', 'Which CSS framework is developed by Twitter?', 'Foundation', 'Bootstrap', 'Materialize', 'Bulma', 'Bootstrap'),
('Frontend', 'What is the correct HTML for making a checkbox?', '<input type="check">', '<input type="checkbox">', '<checkbox>', '<check>', '<input type="checkbox">'),
('Frontend', 'Which symbol is used for comments in JavaScript?', '<!-- -->', '//', '/* */', 'Both // and /* */', 'Both // and /* */'),
('Frontend', 'What is the default position value in CSS?', 'relative', 'absolute', 'static', 'fixed', 'static'),
('Frontend', 'Which method is used to add new elements to an array in JavaScript?', 'push()', 'add()', 'append()', 'insert()', 'push()'),
('Frontend', 'What is the correct HTML for making a dropdown list?', '<list>', '<select>', '<dropdown>', '<input type="list">', '<select>'),
('Frontend', 'Which CSS property is used to add spacing between elements?', 'spacing', 'margin', 'padding', 'Both margin and padding', 'Both margin and padding'),
('Frontend', 'What is the latest version of HTML?', 'HTML4', 'HTML5', 'HTML6', 'XHTML', 'HTML5'),
('Frontend', 'Which JavaScript framework is developed by Facebook?', 'Angular', 'Vue.js', 'React', 'Svelte', 'React'),
('Frontend', 'What does the "box-sizing: border-box" CSS property do?', 'Adds border to box', 'Includes padding and border in element total width', 'Creates a box shadow', 'Sets box dimensions', 'Includes padding and border in element total width'),
('Frontend', 'Which HTML attribute specifies an alternate text for an image?', 'title', 'alt', 'src', 'longdesc', 'alt');

-- JavaScript Questions
INSERT INTO `quiz_questions` (`category`, `question`, `option1`, `option2`, `option3`, `option4`, `answer`) VALUES
('javascript', 'Which company developed JavaScript?', 'Netscape', 'Microsoft', 'Sun Microsystems', 'IBM', 'Netscape'),
('javascript', 'Which symbol is used for comments in JavaScript?', '<!-- -->', '//', '/* */', 'Both // and /* */', 'Both // and /* */'),
('javascript', 'What is the correct syntax for referring to an external JavaScript file?', '<script href="file.js">', '<script name="file.js">', '<script src="file.js">', '<javascript>file.js</javascript>', '<script src="file.js">'),
('javascript', 'Which method is used to add new elements to an array in JavaScript?', 'push()', 'add()', 'append()', 'insert()', 'push()'),
('javascript', 'What does JSON stand for?', 'JavaScript Object Notation', 'Java Standard Object Notation', 'JavaScript Online Notation', 'Java Source Object Notation', 'JavaScript Object Notation'),
('javascript', 'Which JavaScript framework is developed by Facebook?', 'Angular', 'Vue.js', 'React', 'Svelte', 'React'),
('javascript', 'What is the correct way to write a JavaScript array?', 'var colors = "red", "green", "blue"', 'var colors = (1:"red", 2:"green", 3:"blue")', 'var colors = ["red", "green", "blue"]', 'var colors = 1 = ("red"), 2 = ("green"), 3 = ("blue")', 'var colors = ["red", "green", "blue"]'),
('javascript', 'Which built-in method combines the text of two strings and returns a new string?', 'append()', 'concat()', 'attach()', 'None of the above', 'concat()'),
('javascript', 'Which of the following is a correct way to create a Promise in JavaScript?', 'new Promise()', 'Promise.create()', 'create Promise()', 'new createPromise()', 'new Promise()'),
('javascript', 'What is the output of "typeof NaN" in JavaScript?', '"number"', '"NaN"', '"undefined"', '"object"', '"number"');

-- ============================================================
-- USER QUIZ STATUS
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_quiz_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `status` enum('passed','failed') NOT NULL,
  `grooming_completed` tinyint(1) DEFAULT 0,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- GROOMING CONTENT
-- ============================================================
CREATE TABLE IF NOT EXISTS `grooming_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `grooming_content` (`category`, `content`) VALUES
('PHP', '<h4>Core PHP Essentials</h4><ul><li><b>Superglobals:</b> Know $_GET, $_POST, $_SESSION and how to sanitize input.</li><li><b>Sessions & Cookies:</b> session_start(), secure cookie flags, and logout flows.</li><li><b>Security:</b> htmlspecialchars(), prepared statements with mysqli/PDO to prevent XSS/SQLi.</li><li><b>File Uploads:</b> Validate MIME types, size limits, and store outside webroot.</li><li><b>OOP Basics:</b> classes, objects, constructors, visibility, and autoloading.</li></ul>'),
('Java', '<h4>Java Fundamentals</h4><ul><li><b>OOP Pillars:</b> encapsulation, inheritance, polymorphism, abstraction; use of extends/implements.</li><li><b>Exceptions:</b> checked vs unchecked; try/catch/finally; custom exceptions.</li><li><b>Collections:</b> List, Set, Map differences and common implementations.</li><li><b>JVM/JRE/JDK:</b> know the roles and how bytecode is executed.</li><li><b>Basics:</b> static vs instance members; equals/hashCode contract.</li></ul>'),
('Python', '<h4>Python Essentials</h4><ul><li><b>Data Types:</b> list vs tuple (immutability), dict, set.</li><li><b>Functions:</b> def, *args, **kwargs, defaults; lambda basics.</li><li><b>Packages:</b> pip install usage; virtual environments.</li><li><b>Pandas/Numpy:</b> DataFrame basics, when to use numpy arrays.</li><li><b>Style:</b> PEP 8 naming, readable code, and docstrings.</li></ul>'),
('Frontend', '<h4>Frontend Development Basics</h4><ul><li><b>HTML5:</b> semantic tags (header, nav, section), forms, input types.</li><li><b>CSS3:</b> Flexbox/Grid for layouts; box model; responsive design with media queries.</li><li><b>JavaScript:</b> DOM manipulation, event handling, ES6 features (let/const, arrow functions).</li><li><b>Frameworks:</b> basics of Bootstrap for styling; React.js component structure.</li><li><b>Best Practices:</b> accessibility (ARIA), performance optimization (minification, lazy loading).</li></ul>'),
('UI/UX', '<h4>UI/UX Design Principles</h4><ul><li><b>User-Centered Design:</b> focus on user needs, personas, and usability testing.</li><li><b>Wireframing:</b> low-fidelity sketches to high-fidelity prototypes using tools like Figma.</li><li><b>Color Theory:</b> color psychology, contrast ratios for accessibility.</li><li><b>Typography:</b> font pairing, readability, hierarchy.</li><li><b>Interaction Design:</b> feedback, affordances, and intuitive navigation.</li></ul>');

-- ============================================================
-- GROOMING VIDEOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `grooming_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `video_url` varchar(500) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in seconds',
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- GROOMING VIDEOS (matched to quiz questions per job category)
-- ============================================================

-- PHP Videos (Job: PHP Full Stack Developer - Laravel, Eloquent, SQL injection)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('PHP', 'PHP Tutorial for Beginners', 'Learn PHP basics - variables, strings, and functions', 'https://www.youtube.com/embed/ny4-hGENWVk', 1800, 1),
('PHP', 'Laravel Framework Complete Tutorial', 'Build web apps with Laravel - routing, Eloquent ORM, and middleware', 'https://www.youtube.com/embed/l4_Vn-sTBL8', 2400, 2),
('PHP', 'PHP Security - SQL Injection Prevention', 'How SQL injection works and how to prevent it in PHP', 'https://www.youtube.com/embed/sd0U0cVbL0w', 1200, 3);

-- Java Videos (Job: Senior Java Developer - Spring Boot, microservices, JPA, JVM)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Java', 'Java Core Concepts - Inheritance and Collections', 'Learn Java inheritance, collections, and data types', 'https://www.youtube.com/embed/GdzRzWymT4c', 1800, 1),
('Java', 'Spring Boot Tutorial for Beginners', 'Create REST APIs with Spring Boot, annotations and controllers', 'https://www.youtube.com/embed/8cm1x4bC610', 2400, 2),
('Java', 'Microservices Architecture Explained', 'Understand microservices, JPA, JVM, and design patterns', 'https://www.youtube.com/embed/grEKMHGYyns', 1800, 3);

-- Python Videos (Job: Python Backend Developer - Django, ORM, REST, decorators)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Python', 'Python Fundamentals - Functions and Data Structures', 'Learn Python functions, lists, immutability, and PEP 8', 'https://www.youtube.com/embed/rfscVS0vtbw', 1800, 1),
('Python', 'Django Web Framework Tutorial', 'Build web apps with Django ORM, routing, and views', 'https://www.youtube.com/embed/H2EJuAcrZYU', 2400, 2),
('Python', 'Python Decorators and Virtual Environments', 'Master decorators, virtual environments, and REST API', 'https://www.youtube.com/embed/ERCMXc8x7mc', 1200, 3);

-- Frontend Videos (Job: Frontend Developer React - React, JSX, hooks, Redux, CSS, DOM)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Frontend', 'HTML and CSS Crash Course', 'Learn HTML tags, CSS box model, text styling, and layout', 'https://www.youtube.com/embed/qz0aGYrrlhU', 1800, 1),
('Frontend', 'JavaScript DOM Manipulation', 'Understand the DOM, events, and manipulating web pages', 'https://www.youtube.com/embed/W6NZfD5FmYo', 1500, 2),
('Frontend', 'React.js Tutorial for Beginners', 'Learn React, JSX, hooks, virtual DOM, and Redux', 'https://www.youtube.com/embed/Ke90Tje7VS0', 2400, 3);

-- Finance Videos (Job: Financial Analyst - ROI, financial statements, balance sheet)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Finance', 'Financial Statements Explained', 'Income statement, balance sheet, revenue and expenses', 'https://www.youtube.com/embed/Fi1wkUczuyk', 1800, 1),
('Finance', 'ROI and Compound Interest', 'Calculate ROI, understand compound interest and assets', 'https://www.youtube.com/embed/eorpdJUWfTA', 1500, 2),
('Finance', 'Balance Sheet and Financial Analysis', 'Read and interpret balance sheets for analysis', 'https://www.youtube.com/embed/_HK5gpg39pY', 1200, 3);

-- Healthcare Videos (Job: Registered Nurse - CPR, organs, heart rate, MRI)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Healthcare', 'CPR Training Basics', 'Learn CPR technique, steps, and life-saving procedures', 'https://www.youtube.com/embed/QMiV_xoUcWA', 1500, 1),
('Healthcare', 'Human Body Systems and Organs', 'Understanding body organs, detoxification, and vital signs', 'https://www.youtube.com/embed/BQNNOh8c8ks', 1500, 2),
('Healthcare', 'Medical Imaging and Diagnostics', 'How MRI works, vital signs, and vitamin D importance', 'https://www.youtube.com/embed/hblmFtbyYKQ', 1200, 3);

-- Education Videos (Job: High School Teacher - Bloom taxonomy, scaffolding, formative assessment)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Education', 'Bloom Taxonomy and Learning Objectives', 'Understand Bloom taxonomy levels and classify objectives', 'https://www.youtube.com/embed/PUC0UjaLJlQ', 1500, 1),
('Education', 'Learning Styles and Teaching Methods', 'Kinesthetic learning, formative assessment, scaffolding', 'https://www.youtube.com/embed/gb3OuOeM1yk', 1500, 2),
('Education', 'Formative Assessment and Scaffolding', 'Assessment strategies and scaffolding in education', 'https://www.youtube.com/embed/3J12z3Jl_6E', 1200, 3);

-- Engineering Videos (Job: Civil Engineer - Ohm law, semiconductors, CAD, fluid mechanics)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Engineering', 'Ohms Law and Electrical Fundamentals', 'Learn Ohms law, voltage, current, and resistance', 'https://www.youtube.com/embed/yFE4OzPzODs', 1500, 1),
('Engineering', 'Engineering Materials and CAD', 'Semiconductor manufacturing, CAD software, SI units', 'https://www.youtube.com/embed/LfyE1brcEOU', 1500, 2),
('Engineering', 'Fluid Mechanics and Engineering Units', 'Understand fluid flow, force units, calculations', 'https://www.youtube.com/embed/M0MYafumN0I', 1200, 3);

-- Sales Videos (Job: Sales Executive - B2B, sales funnel, cold calling, CRM, USP)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Sales', 'Sales Fundamentals B2B and B2C', 'Understand B2B selling, sales funnel, and CRM', 'https://www.youtube.com/embed/hlrWAiEuxUg', 1500, 1),
('Sales', 'Cold Calling and Sales Techniques', 'Master cold calling, understand USP, close deals', 'https://www.youtube.com/embed/7PLGJujZ3go', 1500, 2),
('Sales', 'CRM and Customer Relationships', 'Using CRM tools and building customer relationships', 'https://www.youtube.com/embed/XJ-dddPl7oo', 1200, 3);

-- HR Videos (Job: HR Coordinator - HRIS, onboarding, attrition, KPI, PTO)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('HR', 'HRIS and HR Technology', 'Understanding HRIS systems, data management, HR tools', 'https://www.youtube.com/embed/Ky0mqAjeDjQ', 1500, 1),
('HR', 'Employee Onboarding and Retention', 'Effective onboarding, reducing attrition, KPI tracking', 'https://www.youtube.com/embed/xO1QesunatA', 1500, 2),
('HR', 'KPIs and Employee Benefits', 'Setting KPIs, understanding PTO policies, compensation', 'https://www.youtube.com/embed/_6ZNID6bWww', 1200, 3);

-- Legal Videos (Job: Legal Counsel - due process, plaintiff, liability, jurisdiction, tort)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Legal', 'Legal System and Due Process', 'Understanding due process, jurisdiction, proceedings', 'https://www.youtube.com/embed/hDQxiT_7T6Q', 1500, 1),
('Legal', 'Civil Law Plaintiff Liability and Torts', 'Plaintiffs, defendants, liability, and tort law', 'https://www.youtube.com/embed/saCwKXaHt6Y', 1500, 2),
('Legal', 'Jurisdiction and Legal Frameworks', 'How jurisdiction works and understanding liability', 'https://www.youtube.com/embed/MxSfGxMqn0Y', 1200, 3);

-- Media Videos (Job: Content Marketing Specialist - SEO, content marketing, CTR, A/B testing)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Media', 'SEO Fundamentals', 'Search engine optimization, keywords, ranking, traffic', 'https://www.youtube.com/embed/g-UuWvyOb1w', 1800, 1),
('Media', 'Content Marketing and Target Audience', 'Creating content, identifying audience, CTR optimization', 'https://www.youtube.com/embed/g1fN1R10mNw', 1500, 2),
('Media', 'A-B Testing and Digital Analytics', 'A-B testing methodology, measuring CTR, analytics', 'https://www.youtube.com/embed/6C4f12kMW3s', 1200, 3);

-- Logistics Videos (Job: Supply Chain Coordinator - FOB, bill of lading, lead time, cross-docking)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Logistics', 'Supply Chain Basics FOB and Shipping', 'FOB terms, bill of lading, shipping documents', 'https://www.youtube.com/embed/lZPO5RclZEo', 1500, 1),
('Logistics', 'Inventory Management and Turnover', 'Inventory turnover, lead time, stock control', 'https://www.youtube.com/embed/qYac28pJ_hc', 1500, 2),
('Logistics', 'Cross-Docking and Distribution', 'Cross-docking, warehouse operations, distribution', 'https://www.youtube.com/embed/V33K1AHhN0U', 1200, 3);

-- Consulting Videos (Job: Management Consultant - SWOT, stakeholder mgmt, KISS, change mgmt)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Consulting', 'SWOT Analysis and Strategic Planning', 'Conduct SWOT analysis and deliverables', 'https://www.youtube.com/embed/xFQMXwE_ci4', 1500, 1),
('Consulting', 'Stakeholder Management and KISS Principle', 'Manage stakeholders and KISS principle in consulting', 'https://www.youtube.com/embed/aPMrV1FVqXs', 1500, 2),
('Consulting', 'Change Management and Presentations', 'Lead change management and present to clients', 'https://www.youtube.com/embed/85Ia4sH0xH0', 1200, 3);

-- Retail Videos (Job: Retail Store Manager - inventory shrinkage, POS, SKU, markup, foot traffic)
INSERT INTO `grooming_videos` (`category`, `title`, `description`, `video_url`, `duration`, `order_index`) VALUES
('Retail', 'Retail Operations and Inventory', 'Inventory shrinkage, SKU management, stock control', 'https://www.youtube.com/embed/ZrdKQJTozPk', 1500, 1),
('Retail', 'POS Systems and Merchandising', 'POS systems, visual merchandising, store layout', 'https://www.youtube.com/embed/7z7iJNP-Lsc', 1500, 2),
('Retail', 'Customer Service Excellence', 'Delivering exceptional customer service in retail', 'https://www.youtube.com/embed/H2K1Yb0BMYo', 1200, 3);

-- ============================================================
-- USER VIDEO PROGRESS
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_video_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `watched_duration` int(11) NOT NULL DEFAULT 0 COMMENT 'Watched duration in seconds',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `last_watched` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_video_unique` (`user_id`, `video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- COMPANIES (job providers)
-- ============================================================
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_email` varchar(255) NOT NULL UNIQUE,
  `company_phone` varchar(20) NOT NULL,
  `company_address` text NOT NULL,
  `company_website` varchar(255) DEFAULT NULL,
  `industry` varchar(100) NOT NULL,
  `company_size` enum('1-10', '11-50', '51-200', '201-500', '501-1000', '1000+') NOT NULL,
  `description` text NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active', 'inactive', 'pending') DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample company data
INSERT INTO `companies` (`company_name`, `company_email`, `company_phone`, `company_address`, `company_website`, `industry`, `company_size`, `description`, `password`) VALUES
('Tech Solutions Inc', 'hr@techsolutions.com', '1234567890', '123 Tech Street, Silicon Valley, CA', 'https://techsolutions.com', 'Information Technology', '51-200', 'Leading software development company specializing in web and mobile applications', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Digital Innovations', 'jobs@digitalinnovations.com', '9876543210', '456 Innovation Blvd, San Francisco, CA', 'https://digitalinnovations.com', 'Software Development', '11-50', 'Innovative startup focused on AI and machine learning solutions', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- COMPANY JOBS (job postings)
-- ============================================================
CREATE TABLE IF NOT EXISTS `company_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `job_category` varchar(100) NOT NULL COMMENT 'e.g., Java Developer, Python Developer, Frontend Developer, PHP Developer',
  `job_description` text NOT NULL,
  `requirements` text NOT NULL,
  `responsibilities` text NOT NULL,
  `location` varchar(255) NOT NULL,
  `employment_type` enum('Full-Time', 'Part-Time', 'Contract', 'Internship') NOT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `experience_required` varchar(50) NOT NULL,
  `skills_required` text NOT NULL,
  `posted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `deadline` date DEFAULT NULL,
  `status` enum('active', 'closed', 'draft') DEFAULT 'active',
  `vacancy_count` int(11) DEFAULT 1,
  `quiz_timer` int(11) NOT NULL DEFAULT 300,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample job postings
INSERT INTO `company_jobs` (`company_id`, `job_title`, `job_category`, `job_description`, `requirements`, `responsibilities`, `location`, `employment_type`, `salary_range`, `experience_required`, `skills_required`, `deadline`, `vacancy_count`) VALUES
(1, 'Senior Java Developer', 'Java', 'We are looking for an experienced Java developer to join our team and work on enterprise applications.', '- Bachelor\'s degree in Computer Science\n- 5+ years of Java experience\n- Strong knowledge of Spring Framework\n- Experience with microservices', '- Design and develop Java applications\n- Write clean, maintainable code\n- Collaborate with cross-functional teams\n- Mentor junior developers', 'San Francisco, CA', 'Full-Time', '$100,000 - $130,000', '5+ years', 'Java, Spring Boot, Microservices, REST APIs, MySQL', '2026-03-31', 2),
(1, 'Python Backend Developer', 'Python', 'Join our team to build scalable backend systems using Python and Django.', '- 3+ years Python experience\n- Django/Flask expertise\n- RESTful API development\n- Database design skills', '- Develop backend APIs\n- Optimize database queries\n- Implement security best practices\n- Write unit tests', 'Remote', 'Full-Time', '$90,000 - $120,000', '3+ years', 'Python, Django, REST APIs, PostgreSQL, Docker', '2026-02-28', 3),
(2, 'Frontend Developer (React)', 'Frontend', 'Looking for a creative frontend developer passionate about building beautiful user interfaces.', '- 2+ years frontend development\n- Expert in React.js\n- HTML5, CSS3, JavaScript\n- Responsive design', '- Build responsive web applications\n- Create reusable components\n- Optimize performance\n- Collaborate with designers', 'New York, NY', 'Full-Time', '$80,000 - $110,000', '2+ years', 'React, JavaScript, HTML5, CSS3, Bootstrap, Redux', '2026-02-15', 2),
(2, 'PHP Full Stack Developer', 'PHP', 'We need a skilled PHP developer to maintain and enhance our web applications.', '- 3+ years PHP experience\n- Laravel framework knowledge\n- MySQL database skills\n- Version control (Git)', '- Develop web applications\n- API development and integration\n- Database optimization\n- Bug fixing and maintenance', 'Los Angeles, CA', 'Contract', '$70,000 - $95,000', '3+ years', 'PHP, Laravel, MySQL, JavaScript, jQuery, Git', '2026-03-15', 1);

-- ============================================================
-- COMPANY JOB QUESTIONS (per-job quizzes)
-- ============================================================
CREATE TABLE IF NOT EXISTS `company_job_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option1` varchar(255) NOT NULL,
  `option2` varchar(255) NOT NULL,
  `option3` varchar(255) NOT NULL,
  `option4` varchar(255) NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  FOREIGN KEY (`job_id`) REFERENCES `company_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Java Developer questions (job_id = 1)
INSERT INTO `company_job_questions` (`job_id`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_answer`) VALUES
(1, 'What is the main benefit of using Spring Boot?', 'Faster development', 'Reduced configuration', 'Built-in server', 'All of the above', 'All of the above'),
(1, 'Which annotation is used to create a REST controller in Spring?', '@Controller', '@RestController', '@Service', '@Component', '@RestController'),
(1, 'What is a microservice?', 'A small application', 'An independent deployable service', 'A database service', 'A testing framework', 'An independent deployable service'),
(1, 'Which design pattern is commonly used in Spring?', 'Singleton', 'Factory', 'Dependency Injection', 'Observer', 'Dependency Injection'),
(1, 'What is JPA?', 'Java Persistence API', 'Java Programming API', 'Java Protocol API', 'Java Package API', 'Java Persistence API');

-- Python Backend Developer questions (job_id = 2)
INSERT INTO `company_job_questions` (`job_id`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_answer`) VALUES
(2, 'Which framework is used for building web applications in Python?', 'Flask', 'Django', 'FastAPI', 'All of the above', 'All of the above'),
(2, 'What is Django ORM used for?', 'Database operations', 'User authentication', 'Template rendering', 'Static file serving', 'Database operations'),
(2, 'What does REST stand for?', 'Representational State Transfer', 'Remote State Transfer', 'Real Estate Transfer', 'Rapid State Transfer', 'Representational State Transfer'),
(2, 'Which HTTP method is used to update a resource?', 'GET', 'POST', 'PUT', 'DELETE', 'PUT'),
(2, 'What is a virtual environment in Python?', 'A cloud service', 'An isolated Python environment', 'A testing tool', 'A database', 'An isolated Python environment');

-- Frontend Developer questions (job_id = 3)
INSERT INTO `company_job_questions` (`job_id`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_answer`) VALUES
(3, 'What is React?', 'A JavaScript library', 'A CSS framework', 'A database', 'A backend framework', 'A JavaScript library'),
(3, 'What is JSX?', 'JavaScript XML', 'Java Syntax Extension', 'JSON XML', 'JavaScript Extension', 'JavaScript XML'),
(3, 'Which hook is used for side effects in React?', 'useState', 'useEffect', 'useContext', 'useMemo', 'useEffect'),
(3, 'What is Redux used for?', 'State management', 'Routing', 'API calls', 'Styling', 'State management'),
(3, 'What is the virtual DOM?', 'A real DOM copy', 'An in-memory representation of real DOM', 'A database', 'A server', 'An in-memory representation of real DOM');

-- PHP Developer questions (job_id = 4)
INSERT INTO `company_job_questions` (`job_id`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_answer`) VALUES
(4, 'What is Laravel?', 'A PHP framework', 'A JavaScript library', 'A database', 'A CSS framework', 'A PHP framework'),
(4, 'Which Laravel component handles routing?', 'Eloquent', 'Blade', 'Route', 'Artisan', 'Route'),
(4, 'What is Eloquent in Laravel?', 'An ORM', 'A template engine', 'A testing tool', 'A CLI tool', 'An ORM'),
(4, 'What command creates a new Laravel project?', 'laravel new', 'composer create-project', 'php artisan new', 'npm create laravel', 'composer create-project'),
(4, 'What is middleware in Laravel?', 'A database layer', 'A filter for HTTP requests', 'A template system', 'A cache system', 'A filter for HTTP requests');

-- ============================================================
-- JOB APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `applied_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `quiz_score` int(11) DEFAULT NULL,
  `quiz_status` enum('not_taken', 'passed', 'failed') DEFAULT 'not_taken',
  `application_status` enum('pending', 'reviewed', 'shortlisted', 'rejected') DEFAULT 'pending',
  `cover_letter` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`),
  FOREIGN KEY (`job_id`) REFERENCES `company_jobs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `user_info`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- JOB QUIZ ATTEMPTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL,
  `score_percentage` decimal(5,2) NOT NULL,
  `attempt_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time in seconds',
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `job_id` (`job_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`application_id`) REFERENCES `job_applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`) REFERENCES `company_jobs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `user_info`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CATEGORY APPLICATIONS (direct category-based applications)
-- ============================================================
CREATE TABLE IF NOT EXISTS `category_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `application_date` datetime DEFAULT current_timestamp(),
  `status` enum('Pending', 'Approved', 'Rejected', 'Interview') DEFAULT 'Pending',
  `company_notes` text DEFAULT NULL,
  `user_message` text DEFAULT NULL,
  `interview_date` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `user_info`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_application` (`user_id`, `company_id`, `category`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_application_date` (`application_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INTERVIEWS
-- ============================================================
CREATE TABLE IF NOT EXISTS `interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `interview_type` enum('Online','Phone','In-Person') NOT NULL DEFAULT 'Online',
  `location` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `company_id` (`company_id`),
  KEY `user_id` (`user_id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('user', 'company', 'admin') NOT NULL COMMENT 'Who receives this notification',
  `recipient_id` int(11) NOT NULL COMMENT 'ID of the recipient',
  `sender_type` enum('user', 'company', 'admin', 'system') NOT NULL DEFAULT 'system',
  `sender_id` int(11) DEFAULT NULL COMMENT 'ID of the sender',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('application_status', 'new_application', 'message', 'quiz_result', 'job_update', 'system', 'job_recommendation') NOT NULL DEFAULT 'system',
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `recipient` (`recipient_type`, `recipient_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MESSAGES (direct messaging)
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_type` enum('user', 'company', 'admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('user', 'company', 'admin') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted_by_sender` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted_by_receiver` tinyint(1) NOT NULL DEFAULT 0,
  `related_job_id` int(11) DEFAULT NULL COMMENT 'Optional link to a job posting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conversation` (`sender_type`, `sender_id`, `receiver_type`, `receiver_id`),
  KEY `receiver` (`receiver_type`, `receiver_id`, `is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- LIVE CHATS (real-time chat between users and companies)
-- ============================================================
CREATE TABLE IF NOT EXISTS `live_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_type` enum('user', 'company', 'admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('user', 'company', 'admin') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conversation` (`sender_type`, `sender_id`, `receiver_type`, `receiver_id`),
  KEY `receiver` (`receiver_type`, `receiver_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SAVED JOBS
-- ============================================================
CREATE TABLE IF NOT EXISTS `saved_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_save` (`user_id`, `job_id`),
  KEY `user_id` (`user_id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NEWSLETTER SUBSCRIBERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL UNIQUE,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- JOB VIEWS (analytics)
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SAMPLE NOTIFICATIONS
-- ============================================================
INSERT INTO `notifications` (`recipient_type`, `recipient_id`, `sender_type`, `sender_id`, `title`, `message`, `notification_type`, `related_type`, `related_id`, `is_read`, `created_at`) VALUES
('user', 1, 'system', NULL, 'Welcome to NovaHire!', 'Complete your profile and take a skill assessment to start applying for jobs.', 'system', NULL, NULL, 0, NOW()),
('user', 1, 'company', 1, 'Application Received', 'Your application for Senior Java Developer at Tech Solutions Inc has been received.', 'application_status', 'job_applications', 1, 0, NOW()),
('company', 1, 'user', 1, 'New Application', 'John Doe has applied for Senior Java Developer position.', 'new_application', 'job_applications', 1, 0, NOW());

-- ============================================================
-- SAMPLE MESSAGES
-- ============================================================
INSERT INTO `messages` (`sender_type`, `sender_id`, `receiver_type`, `receiver_id`, `subject`, `message`, `is_read`, `related_job_id`, `created_at`) VALUES
('company', 1, 'user', 1, 'Regarding Your Application', 'We have reviewed your application and would like to schedule an interview. Please let us know your availability.', 0, 1, NOW()),
('user', 1, 'company', 1, 'Re: Regarding Your Application', 'Thank you for considering my application. I am available any day next week between 10 AM and 4 PM.', 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY));

COMMIT;
