-- Add new job categories with sample jobs and quiz questions
-- Run: /Applications/XAMPP/xamppfiles/bin/mysql -u root projects < job_categories_v2.sql

-- ==================== NEW SAMPLE JOBS FOR NEW CATEGORIES ====================

INSERT INTO company_jobs (company_id, job_title, job_category, job_description, requirements, responsibilities, location, employment_type, salary_range, experience_required, skills_required, deadline, vacancy_count, quiz_timer, status) VALUES
(1, 'Financial Analyst', 'Finance', 'Analyze financial data, prepare reports, and support decision-making processes for the organization.', 'Bachelor degree in Finance or Accounting. Proficiency in Excel and financial modeling.', 'Prepare monthly financial reports. Analyze budget variances. Support forecasting models.', 'Dhaka, Bangladesh', 'Full-Time', '40,000 - 60,000 BDT', '2-3 years', 'Financial Modeling,Excel,Accounting,Budgeting,Forecasting', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 3, 15, 'active'),

(2, 'Registered Nurse', 'Healthcare', 'Provide patient care, administer medications, and support doctors in clinical settings.', 'BSN degree. Valid nursing license. 1+ years clinical experience.', 'Monitor patient health. Administer medications. Maintain patient records.', 'Chittagong, Bangladesh', 'Full-Time', '30,000 - 45,000 BDT', '1-2 years', 'Patient Care,Medical Terminology,EMR,CPR,Communication', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 5, 15, 'active'),

(1, 'High School Teacher', 'Education', 'Teach curriculum to students, prepare lesson plans, and assess student progress.', 'Bachelor degree in Education. Teaching certification. Strong communication skills.', 'Develop lesson plans. Grade assignments. Communicate with parents.', 'Sylhet, Bangladesh', 'Full-Time', '25,000 - 40,000 BDT', '2-4 years', 'Teaching,Curriculum Design,Classroom Management,Assessment,Communication', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 15, 'active'),

(2, 'Civil Engineer', 'Engineering', 'Design and oversee construction projects, ensuring compliance with safety standards.', 'BSc in Civil Engineering. AutoCAD proficiency. Project management skills.', 'Design structural plans. Supervise construction sites. Ensure code compliance.', 'Rajshahi, Bangladesh', 'Full-Time', '45,000 - 70,000 BDT', '3-5 years', 'AutoCAD,Structural Design,Project Management,MS Project,Engineering', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 15, 'active'),

(1, 'Sales Executive', 'Sales', 'Drive revenue growth by identifying opportunities and building client relationships.', 'Bachelor degree. Excellent negotiation skills. Self-motivated.', 'Generate leads. Close deals. Maintain client relationships. Meet sales targets.', 'Dhaka, Bangladesh', 'Full-Time', '30,000 - 50,000 BDT + Commission', '1-3 years', 'Sales,Negotiation,CRM,Communication,Lead Generation', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 4, 15, 'active'),

(2, 'HR Coordinator', 'HR', 'Support recruitment, onboarding, and employee relations activities.', 'Bachelor in HR or Business. Knowledge of labor laws. Interpersonal skills.', 'Coordinate interviews. Process new hires. Maintain employee records.', 'Dhaka, Bangladesh', 'Full-Time', '28,000 - 42,000 BDT', '1-2 years', 'Recruitment,Onboarding,HRIS,Communication,MS Office', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 15, 'active'),

(1, 'Legal Counsel', 'Legal', 'Provide legal advice, draft contracts, and ensure regulatory compliance.', 'LLB degree. Bar admission. 3+ years legal experience.', 'Draft legal documents. Advise on compliance. Represent in legal matters.', 'Dhaka, Bangladesh', 'Full-Time', '60,000 - 90,000 BDT', '3-5 years', 'Contract Law,Legal Research,Drafting,Compliance,Litigation', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, 15, 'active'),

(2, 'Content Marketing Specialist', 'Media', 'Create and manage content strategies to engage audiences and drive brand awareness.', 'Bachelor in Marketing or Communications. Portfolio required.', 'Create blog posts. Manage social media. Analyze content performance.', 'Dhaka, Bangladesh', 'Full-Time', '30,000 - 45,000 BDT', '2-3 years', 'Content Writing,SEO,Social Media,Analytics,Marketing', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 15, 'active'),

(1, 'Supply Chain Coordinator', 'Logistics', 'Manage inventory, coordinate shipments, and optimize supply chain operations.', 'Bachelor in Business or Logistics. Strong analytical skills.', 'Track shipments. Manage inventory. Coordinate with vendors.', 'Chittagong, Bangladesh', 'Full-Time', '32,000 - 48,000 BDT', '2-3 years', 'Inventory Management,Supply Chain,ERP,Excel,Communication', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 15, 'active'),

(2, 'Management Consultant', 'Consulting', 'Advise organizations on strategy, operations, and organizational challenges.', 'MBA preferred. Strong analytical and problem-solving skills.', 'Analyze business problems. Develop solutions. Present recommendations.', 'Dhaka, Bangladesh', 'Full-Time', '55,000 - 85,000 BDT', '3-5 years', 'Strategy,Problem Solving,Excel,PowerPoint,Communication', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 15, 'active'),

(1, 'Retail Store Manager', 'Retail', 'Oversee store operations, manage staff, and ensure customer satisfaction.', 'Bachelor in Business. Retail management experience. Leadership skills.', 'Manage daily operations. Supervise staff. Handle inventory and sales.', 'Dhaka, Bangladesh', 'Full-Time', '35,000 - 50,000 BDT', '3-5 years', 'Retail Management,Leadership,Inventory,Customer Service,POS', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, 15, 'active');

-- ==================== QUIZ QUESTIONS FOR EXISTING JOBS ====================

-- Job ID 1: Senior Java Developer (Java)
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(1, 'What is JVM?', 'Java Virtual Machine', 'Java Variable Manager', 'Joint Vector Module', 'Java Version Manager', 'Java Virtual Machine'),
(1, 'Which keyword is used to inherit a class in Java?', 'extends', 'implements', 'inherits', 'super', 'extends'),
(1, 'What is the default value of an int variable in Java?', '0', 'null', 'undefined', '1', '0'),
(1, 'Which collection does not allow duplicate elements?', 'ArrayList', 'HashSet', 'LinkedList', 'Vector', 'HashSet'),
(1, 'What is the size of an int in Java?', '2 bytes', '4 bytes', '8 bytes', '16 bytes', '4 bytes');

-- Job ID 2: Python Backend Developer (Python)
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(2, 'What is PEP 8?', 'A Python version', 'Style guide for Python code', 'A testing framework', 'A package manager', 'Style guide for Python code'),
(2, 'Which keyword is used to define a function in Python?', 'function', 'func', 'def', 'define', 'def'),
(2, 'What does len() return for an empty list?', '0', 'None', 'False', 'Error', '0'),
(2, 'Which of these is immutable in Python?', 'List', 'Dictionary', 'Tuple', 'Set', 'Tuple'),
(2, 'What is a decorator in Python?', 'A comment', 'A function that modifies another function', 'A variable type', 'A loop structure', 'A function that modifies another function');

-- Job ID 3: Frontend Developer (Frontend)
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(3, 'What does CSS stand for?', 'Creative Style Sheets', 'Cascading Style Sheets', 'Computer Style Sheets', 'Colorful Style Sheets', 'Cascading Style Sheets'),
(3, 'Which HTML tag is used for the largest heading?', '<heading>', '<h6>', '<h1>', '<head>', '<h1>'),
(3, 'What is the box model in CSS?', 'A design layout', 'Content, padding, border, margin', 'A JavaScript object', 'An HTML element', 'Content, padding, border, margin'),
(3, 'Which property is used to change text color?', 'font-color', 'text-color', 'color', 'foreground', 'color'),
(3, 'What does DOM stand for?', 'Document Object Model', 'Data Object Manager', 'Digital Output Mode', 'Document Order Method', 'Document Object Model');

-- Job ID 4: PHP Full Stack Developer (PHP)
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(4, 'What does PHP stand for?', 'Personal Home Page', 'PHP: Hypertext Preprocessor', 'Private Home Page', 'Public Hosting Protocol', 'PHP: Hypertext Preprocessor'),
(4, 'Which symbol is used to start a variable in PHP?', '@', '#', '$', '&', '$'),
(4, 'What is the default port for Apache?', '80', '8080', '443', '3000', '80'),
(4, 'Which function is used to get the length of a string?', 'length()', 'strlen()', 'str_length()', 'count()', 'strlen()'),
(4, 'What does SQL injection target?', 'The browser', 'The database through input fields', 'The server hardware', 'The CSS file', 'The database through input fields');

-- ==================== QUIZ QUESTIONS FOR NEW JOBS (IDs 5-15) ====================

-- Job ID 5: Financial Analyst
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(5, 'What does ROI stand for?', 'Return on Investment', 'Rate of Interest', 'Revenue on Income', 'Ratio of Inflation', 'Return on Investment'),
(5, 'Which financial statement shows revenue and expenses?', 'Balance Sheet', 'Income Statement', 'Cash Flow Statement', 'Equity Statement', 'Income Statement'),
(5, 'What is the primary purpose of a balance sheet?', 'Show profits', 'Show assets, liabilities, and equity at a point in time', 'Show cash flow', 'Show tax obligations', 'Show assets, liabilities, and equity at a point in time'),
(5, 'What is compound interest?', 'Interest on original principal only', 'Interest on principal plus accumulated interest', 'A fixed annual rate', 'Interest paid monthly', 'Interest on principal plus accumulated interest'),
(5, 'Which is NOT a type of financial asset?', 'Stock', 'Bond', 'Real Estate', 'Mutual Fund', 'Real Estate');

-- Job ID 6: Registered Nurse
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(6, 'What does CPR stand for?', 'Cardiac Pressure Relief', 'Cardiopulmonary Resuscitation', 'Central Pulse Recovery', 'Cardio Phase Reset', 'Cardiopulmonary Resuscitation'),
(6, 'Which organ primarily detoxifies the body?', 'Heart', 'Kidneys', 'Liver', 'Lungs', 'Liver'),
(6, 'What is the normal resting heart rate for adults?', '40-50 bpm', '60-100 bpm', '100-120 bpm', '120-150 bpm', '60-100 bpm'),
(6, 'Which vitamin is produced when skin is exposed to sunlight?', 'Vitamin A', 'Vitamin B12', 'Vitamin C', 'Vitamin D', 'Vitamin D'),
(6, 'What does MRI stand for?', 'Medical Resonance Imaging', 'Magnetic Resonance Imaging', 'Multi-Range Inspection', 'Molecular Resonance Index', 'Magnetic Resonance Imaging');

-- Job ID 7: High School Teacher
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(7, 'Who is considered the father of modern education?', 'Aristotle', 'John Dewey', 'Socrates', 'Plato', 'John Dewey'),
(7, 'What does Blooms Taxonomy classify?', 'Learning objectives', 'Teaching methods', 'Student grades', 'School types', 'Learning objectives'),
(7, 'Which learning style involves learning by doing?', 'Visual', 'Auditory', 'Kinesthetic', 'Reading/Writing', 'Kinesthetic'),
(7, 'What is formative assessment?', 'Final exam', 'Ongoing assessment during learning', 'Standardized test', 'Grading system', 'Ongoing assessment during learning'),
(7, 'What is the scaffolding technique?', 'Building physical structures', 'Providing temporary support to learners', 'Testing methods', 'Classroom management', 'Providing temporary support to learners');

-- Job ID 8: Civil Engineer
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(8, 'What is Ohms Law?', 'V = IR', 'V = I/R', 'V = I+R', 'V = I-R', 'V = IR'),
(8, 'Which material is most used in semiconductor manufacturing?', 'Copper', 'Silicon', 'Iron', 'Aluminum', 'Silicon'),
(8, 'What is the SI unit of force?', 'Joule', 'Watt', 'Newton', 'Pascal', 'Newton'),
(8, 'What does CAD stand for?', 'Computer Aided Design', 'Central Application Device', 'Computer Automated Drafting', 'Calculated Aid Design', 'Computer Aided Design'),
(8, 'Which branch deals with fluid flow?', 'Thermodynamics', 'Fluid Mechanics', 'Structural Analysis', 'Dynamics', 'Fluid Mechanics');

-- Job ID 9: Sales Executive
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(9, 'What does B2B stand for?', 'Business to Browser', 'Business to Business', 'Brand to Brand', 'Budget to Budget', 'Business to Business'),
(9, 'What is a sales funnel?', 'A kitchen tool', 'A visual representation of the customer journey', 'A type of contract', 'An accounting method', 'A visual representation of the customer journey'),
(9, 'What is cold calling?', 'Calling in winter', 'Contacting potential customers who have not expressed interest', 'Calling after hours', 'Calling competitors', 'Contacting potential customers who have not expressed interest'),
(9, 'What is CRM?', 'Customer Relationship Management', 'Company Revenue Model', 'Client Retention Method', 'Corporate Resource Management', 'Customer Relationship Management'),
(9, 'What is a USP?', 'Unique Selling Proposition', 'Universal Sales Plan', 'Unified Service Platform', 'Ultimate Sales Price', 'Unique Selling Proposition');

-- Job ID 10: HR Coordinator
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(10, 'What does HRIS stand for?', 'Human Resource Information System', 'Human Relations in Industry Society', 'Hiring Rules and Internal Standards', 'High Revenue Investment Strategy', 'Human Resource Information System'),
(10, 'What is onboarding?', 'Logging into a system', 'The process of integrating a new employee', 'Sending emails', 'Building maintenance', 'The process of integrating a new employee'),
(10, 'What is employee attrition?', 'Promotion', 'The rate at which employees leave', 'Hiring new staff', 'Training programs', 'The rate at which employees leave'),
(10, 'What is a KPI?', 'Key Performance Indicator', 'Kilo Pixel Index', 'Known Problem Identifier', 'Key Personnel Information', 'Key Performance Indicator'),
(10, 'What does PTO stand for?', 'Paid Time Off', 'Personal Task Organizer', 'Project Timeline Overview', 'Professional Training Option', 'Paid Time Off');

-- Job ID 11: Legal Counsel
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(11, 'What is due process?', 'Fast court proceedings', 'Fair treatment through the judicial system', 'Paying court fees on time', 'Presenting evidence', 'Fair treatment through the judicial system'),
(11, 'What is a plaintiff?', 'The judge', 'The person who files a lawsuit', 'The lawyer', 'The jury', 'The person who files a lawsuit'),
(11, 'What is liability?', 'Assets owned', 'Legal responsibility for something', 'A court order', 'A type of insurance', 'Legal responsibility for something'),
(11, 'What is jurisdiction?', 'A type of lawyer', 'The authority of a court to hear a case', 'A legal document', 'A punishment', 'The authority of a court to hear a case'),
(11, 'What is a tort?', 'A type of contract', 'A civil wrong causing harm', 'A criminal charge', 'A court ruling', 'A civil wrong causing harm');

-- Job ID 12: Content Marketing Specialist
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(12, 'What does SEO stand for?', 'Social Engine Optimization', 'Search Engine Optimization', 'System Enhancement Operation', 'Site Evaluation Overview', 'Search Engine Optimization'),
(12, 'What is content marketing?', 'Selling content', 'Creating valuable content to attract audiences', 'Buying ad space', 'Writing news articles', 'Creating valuable content to attract audiences'),
(12, 'What is a target audience?', 'All people', 'A specific group of people a message is intended for', 'Competitors', 'Media buyers', 'A specific group of people a message is intended for'),
(12, 'What does CTR measure?', 'Click-Through Rate', 'Customer Turnover Ratio', 'Content Transfer Rate', 'Campaign Tracking Report', 'Click-Through Rate'),
(12, 'What is A/B testing?', 'Testing two versions', 'Alpha and Beta testing', 'Academic testing', 'Automated binary testing', 'Testing two versions');

-- Job ID 13: Supply Chain Coordinator
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(13, 'What does FOB stand for?', 'Free on Board', 'Freight or Buffer', 'Full Order Batch', 'Fixed Operating Budget', 'Free on Board'),
(13, 'What is a Bill of Lading?', 'A shipping invoice', 'A legal document between shipper and carrier', 'A customs form', 'A receipt', 'A legal document between shipper and carrier'),
(13, 'What is lead time?', 'Time to lead a team', 'Time between order placement and delivery', 'Meeting time', 'Training duration', 'Time between order placement and delivery'),
(13, 'What is cross-docking?', 'Docking twice', 'Transferring goods directly from inbound to outbound', 'Loading dock inspection', 'Using multiple docks', 'Transferring goods directly from inbound to outbound'),
(13, 'What is inventory turnover?', 'Changing inventory locations', 'How many times inventory is sold and replaced over a period', 'Moving stock', 'Counting items', 'How many times inventory is sold and replaced over a period');

-- Job ID 14: Management Consultant
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(14, 'What is a SWOT analysis?', 'A financial audit', 'Analysis of Strengths, Weaknesses, Opportunities, Threats', 'A software tool', 'A hiring method', 'Analysis of Strengths, Weaknesses, Opportunities, Threats'),
(14, 'What is stakeholder management?', 'Managing money', 'Identifying and engaging people affected by a project', 'Managing servers', 'Stock management', 'Identifying and engaging people affected by a project'),
(14, 'What is a deliverable?', 'A shipping package', 'A tangible or intangible product of project work', 'A payment', 'A contract', 'A tangible or intangible product of project work'),
(14, 'What does KISS principle mean?', 'Keep It Simple, Stupid', 'Key Information for Smart Solutions', 'Knowledge Integration for Strategic Success', 'Keep Informed, Stay Smart', 'Keep It Simple, Stupid'),
(14, 'What is change management?', 'Managing finances', 'A structured approach to transitioning individuals or organizations', 'Changing jobs', 'Updating software', 'A structured approach to transitioning individuals or organizations');

-- Job ID 15: Retail Store Manager
INSERT INTO company_job_questions (job_id, question, option1, option2, option3, option4, correct_answer) VALUES
(15, 'What is inventory shrinkage?', 'Clothes getting smaller', 'Loss of inventory due to theft, damage, or errors', 'Reducing stock levels', 'Seasonal discounts', 'Loss of inventory due to theft, damage, or errors'),
(15, 'What does POS stand for?', 'Point of Sale', 'Product or Service', 'Price of Stock', 'Profit or Savings', 'Point of Sale'),
(15, 'What is a SKU?', 'Stock Keeping Unit', 'Sales Knowledge Unit', 'Store Key Utility', 'Standard Kit Usage', 'Stock Keeping Unit'),
(15, 'What is markup?', 'Writing on a box', 'The amount added to cost to determine selling price', 'A type of discount', 'Product labeling', 'The amount added to cost to determine selling price'),
(15, 'What is foot traffic?', 'People walking', 'The number of people entering a retail space', 'Floor maintenance', 'Delivery route', 'The number of people entering a retail space');
