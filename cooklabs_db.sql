-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 04:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cooklabs_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `attempts_allowed` int(11) DEFAULT NULL,
  `passing_score` int(11) DEFAULT 75,
  `question_count` int(11) DEFAULT 0,
  `total_points` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `course_id`, `title`, `description`, `time_limit`, `attempts_allowed`, `passing_score`, `question_count`, `total_points`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 2, 'Training Regulation Post Test', '', NULL, NULL, 75, 4, 0, 1, '2026-03-12 14:01:52', '2026-03-13 07:42:10'),
(7, 23, 'Post-course Assessment for Cookery NC II Module - Chapter 1', 'Choose the letter of the correct answer.', NULL, NULL, 60, 10, 0, 42, '2026-04-16 13:32:17', '2026-04-16 14:02:28'),
(8, 24, 'Post-course assessment for Cookery NC II - Chapter II', 'Choose the correct answer.', NULL, NULL, 75, 10, 0, 42, '2026-04-16 13:40:45', '2026-04-16 13:40:57'),
(9, 25, 'Post-course assessment for Cookery NC II - Chapter III', 'Choose the correct answer.', NULL, NULL, 75, 10, 0, 42, '2026-04-16 13:45:46', '2026-04-16 13:46:05'),
(10, 26, 'Post-course assessment for Cookery NC II - Chapter IV', 'Choose the correct answer.', NULL, NULL, 75, 10, 0, 42, '2026-04-16 13:51:15', '2026-04-16 13:51:25'),
(11, 27, 'Post-course assessment for Cookery NC II - Chapter V - Lesson 1', 'Coose the correct answer.', NULL, NULL, 75, 10, 0, 42, '2026-04-16 13:55:07', NULL),
(12, 28, 'Post-course assessment for Cookery NC II - Chapter V - Lesson 2', 'Choose the correct answer.', NULL, NULL, 75, 9, 0, 42, '2026-04-16 14:00:39', NULL),
(13, 16, 'Test Assessment', 'Test Assessment', NULL, NULL, 50, 4, 0, 1, '2026-04-16 14:06:12', '2026-04-16 14:06:25');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_answers`
--

CREATE TABLE `assessment_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` char(1) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_answers`
--

INSERT INTO `assessment_answers` (`id`, `attempt_id`, `question_id`, `selected_option`, `is_correct`, `points_earned`, `answered_at`) VALUES
(41, 12, 8, 'C', 1, 1, '2026-03-19 07:41:16'),
(42, 12, 5, 'D', 1, 1, '2026-03-19 07:41:19'),
(43, 12, 6, 'C', 0, 0, '2026-03-19 07:41:21'),
(44, 12, 7, 'A', 1, 1, '2026-03-19 07:41:23'),
(46, 15, 8, 'C', 1, 1, '2026-03-24 11:20:50'),
(47, 15, 7, 'A', 1, 1, '2026-03-24 11:20:55'),
(48, 15, 5, 'D', 1, 1, '2026-03-24 11:20:59'),
(49, 15, 6, 'D', 1, 1, '2026-03-24 11:21:07'),
(62, 19, 5, 'B', 0, 0, '2026-03-27 10:14:50'),
(63, 19, 6, 'B', 0, 0, '2026-03-27 10:14:52'),
(64, 19, 7, 'A', 1, 1, '2026-03-27 10:14:55'),
(65, 19, 8, 'C', 1, 1, '2026-03-27 10:14:57'),
(66, 20, 8, 'C', 1, 1, '2026-03-27 10:15:08'),
(67, 20, 7, 'A', 1, 1, '2026-03-27 10:15:10'),
(68, 20, 6, 'D', 1, 1, '2026-03-27 10:15:12'),
(69, 20, 5, 'B', 0, 0, '2026-03-27 10:15:14');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_attempts`
--

CREATE TABLE `assessment_attempts` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempt_number` int(11) DEFAULT 1,
  `score` decimal(5,2) DEFAULT 0.00,
  `total_points` int(11) DEFAULT 0,
  `earned_points` int(11) DEFAULT 0,
  `status` enum('in_progress','completed','timeout') DEFAULT 'in_progress',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `time_spent` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_attempts`
--

INSERT INTO `assessment_attempts` (`id`, `assessment_id`, `user_id`, `attempt_number`, `score`, `total_points`, `earned_points`, `status`, `started_at`, `completed_at`, `time_spent`) VALUES
(12, 2, 27, 1, 75.00, 0, 3, 'completed', '2026-03-19 07:41:08', '2026-03-19 07:41:25', 0),
(13, 2, 27, 1, 0.00, 0, NULL, 'completed', '2026-03-19 07:42:15', '2026-03-19 07:42:55', 0),
(15, 2, 27, 1, 100.00, 0, 4, 'completed', '2026-03-24 11:20:45', '2026-03-24 11:21:11', 0),
(19, 2, 31, 1, 50.00, 0, 2, 'completed', '2026-03-27 10:14:47', '2026-03-27 10:14:59', 0),
(20, 2, 31, 1, 75.00, 0, 3, 'completed', '2026-03-27 10:15:06', '2026-03-27 10:15:16', 0),
(21, 7, 43, 1, 50.00, 0, 5, 'completed', '2026-04-16 14:01:22', '2026-04-16 14:01:49', 0);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_option` char(1) NOT NULL,
  `points` int(11) DEFAULT 1,
  `order_num` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `points`, `order_num`, `created_at`) VALUES
(5, 2, 'Who is Imman', 'Emman', 'Backend', 'Bisaya', 'Regine Velasquez', 'D', 1, 1, '2026-03-13 07:42:10'),
(6, 2, 'Who is Mika', 'Akim', 'Azalea', 'Mikasaurusussy', 'All of the Above', 'D', 1, 2, '2026-03-13 07:42:10'),
(7, 2, 'Who is Marga', 'Dauzu', 'Margie', 'Blush on', 'Pushable', 'A', 1, 3, '2026-03-13 07:42:10'),
(8, 2, 'Who is Kooky', 'Scammer', 'Kookycoin', 'Momi Oni Top Fan 1000%', 'Kokey', 'C', 1, 4, '2026-03-13 07:42:10'),
(42, 8, 'What is the French term that means \"set in place\" and refers to having all ingredients and tools ready before cooking?', 'Hors d\'oeuvres', 'Mise en place', 'Aperitif', 'Canapé', 'B', 1, 1, '2026-04-16 13:40:57'),
(43, 8, 'A Canapé consists of three parts. Which of the following is the correct order?', 'Garnish, Spread, Base', 'Base, Spread, Garnish', 'Spread, Base, Garnish', 'Base, Garnish, Spread', 'B', 1, 2, '2026-04-16 13:40:57'),
(44, 8, 'Which tool is specifically described as a \"sharp edged scoop for cutting out balls of fruits and vegetables\"?', 'Ball cutter', 'Channel knife', 'Paring knife', 'Rubber spatula', 'A', 1, 3, '2026-04-16 13:40:57'),
(45, 8, 'What is the purpose of an \"aperitif\" as introduced by the Romans?', 'A small sandwich served before dessert', 'A pickled vegetable to cleanse the palate', 'A liquid appetizer containing alcohol to stimulate appetite and aid digestion', 'A fruit platter served at the end of the meal', 'C', 1, 4, '2026-04-16 13:40:57'),
(46, 8, 'Which classification of appetizer is described as \"small portions of highly seasoned foods\" that may include canapés, olives, stuffed celery, and pickled radishes?', 'Cocktails', 'Hors d\'oeuvres', 'Petite salads', 'Relishes', 'B', 1, 5, '2026-04-16 13:40:57'),
(47, 8, 'What is the recommended guideline for assembling canapés to prevent bases from becoming soggy?', 'Assemble them 4 hours before service to allow flavors to meld', 'Store assembled canapés at room temperature', 'Assemble as close as possible to serving time', 'Soak the base in water before adding spread', 'C', 1, 6, '2026-04-16 13:40:57'),
(48, 8, 'Raw vegetables cut into attractive bite-size shapes served with dips are known as:', 'Canapés', 'Crudités', 'Cocktails', 'Relishes only', 'B', 1, 7, '2026-04-16 13:40:57'),
(49, 8, 'According to the module, where should hot hors d\'oeuvres traditionally be served in a classical menu sequence?', 'Between the soup and fish course', 'As the first course before soup', 'As a replacement for dessert', 'Only at cocktail parties, never during meals', 'A', 1, 8, '2026-04-16 13:40:57'),
(50, 8, 'Which of the following is NOT one of the fundamentals of plating mentioned in the module?', 'Balance of color, shape, and texture', 'Portion size matching the plate', 'Simple arrangement being more attractive than complicated designs', 'Always placing the centerpiece exactly in the middle of the platter', 'D', 1, 9, '2026-04-16 13:40:57'),
(51, 8, 'What is the function of a \"Channel knife\" in preparing appetizers?', 'Cutting out balls of fruits', 'Scraping contents off bowls', 'Trimming and paring fruits', 'Making garnishes', 'D', 1, 10, '2026-04-16 13:40:57'),
(72, 9, 'Which type of salad is defined as \"mixtures of foods that are held together or bound with a dressing, usually a thick dressing like mayonnaise\"?', 'Composed salad', 'Gelatin salad', 'Bound salad', 'Green salad', 'C', 1, 1, '2026-04-16 13:46:05'),
(73, 9, 'What is the recommended ratio of oil to vinegar in a basic vinaigrette dressing?', '1 part oil to 3 parts vinegar', '3 parts oil to 1 part vinegar', '2 parts oil to 2 parts vinegar', '4 parts oil to 1 part vinegar', 'B', 1, 2, '2026-04-16 13:46:05'),
(74, 9, 'Which tool is described as approximately four inches long with a curved metal end perforated with sharpened round holes, used to remove the colored outer layer of citrus fruits?', 'Peeler', 'Citrus zester', 'Grater', 'Channel knife', 'B', 1, 3, '2026-04-16 13:46:05'),
(75, 9, 'A simple oil and vinegar dressing is called a \"temporary emulsion\" because:', 'It must be used within 24 hours', 'It requires heat to combine properly', 'The two liquids always separate after being shaken', 'It contains egg yolk as a stabilizer', 'C', 1, 4, '2026-04-16 13:46:05'),
(76, 9, 'According to the module, what should be done to potatoes before peeling and cutting them for potato salad?', 'Peel them raw and boil in salted water', 'Cut them first, then boil for faster cooking', 'Cook them whole before peeling to preserve nutrients', 'Soak them in vinegar to prevent discoloration', 'C', 1, 5, '2026-04-16 13:46:05'),
(77, 9, 'Which of the following is the correct procedure for dissolving unflavored gelatin?', 'Stir it directly into boiling water', 'Stir it in cold liquid first, let stand for 5 minutes, then heat until dissolved', 'Add it to hot milk and whisk vigorously', 'Mix it with sugar before adding liquid', 'B', 1, 6, '2026-04-16 13:46:05'),
(78, 9, 'What are the four parts of a plated salad\'s structure?', 'Greens, Vegetables, Protein, Dressing', 'Top, Middle, Bottom, Side', 'Base/Underliner, Body, Garnish, Dressing', 'Foundation, Filling, Topping, Sauce', 'C', 1, 7, '2026-04-16 13:46:05'),
(79, 9, 'Which of the following is a guideline for presenting salads attractively?', 'Spread ingredients all the way to the rim of the plate', 'Mix all ingredients thoroughly so nothing is identifiable', 'Keep the salad off the rim of the plate and strive for a good balance of colors', 'Always add dressing at least one hour before serving', 'C', 1, 8, '2026-04-16 13:46:05'),
(80, 9, 'Mayonnaise is classified as a \"permanent emulsion\" because:', 'It has a longer shelf life than vinaigrette', 'It must be stored permanently in the refrigerator', 'It contains egg yolk which forms a layer around oil droplets and holds them in suspension', 'It cannot be separated by any means once mixed', 'C', 1, 9, '2026-04-16 13:46:05'),
(81, 9, 'According to the safety and hygienic practices for storing salads, when should dressing be added to green salads?', 'At least 2 hours before serving to allow flavors to develop', 'The night before and refrigerated', 'Immediately before serving, or served on the side', 'While the greens are still warm', 'C', 1, 10, '2026-04-16 13:46:05'),
(92, 10, 'What are the three basic components of a sandwich?', 'Bread, Meat, Cheese', 'Structure/Base, Moistening Agent, Filling', 'Top Slice, Filling, Bottom Slice', 'Spread, Protein, Garnish', 'B', 1, 1, '2026-04-16 13:51:25'),
(93, 10, 'Which type of knife is specifically designed with a plastic serrated edge to slice lettuce without causing the edges to turn brown?', 'Deli knife', 'Paring knife', 'Lettuce knife', 'Sandwich knife', 'C', 1, 2, '2026-04-16 13:51:25'),
(94, 10, 'What is the primary purpose of spreads such as butter or mayonnaise in sandwich making?', 'To add extra calories for energy', 'To make the sandwich look glossy', 'To protect the bread from soaking up moisture from the filling', 'To act as the main flavor component', 'C', 1, 3, '2026-04-16 13:51:25'),
(95, 10, 'Which type of sandwich is made by placing buttered or unbuttered bread on a serving plate, covering it with hot meat or filling, and topping with sauce, gravy, or cheese?', 'Regular cold sandwich', 'Grilled sandwich', 'Hot open-faced sandwich', 'Pinwheel sandwich', 'C', 1, 4, '2026-04-16 13:51:25'),
(96, 10, 'Pinwheel sandwiches are made by:', 'Stacking three slices of bread with multiple fillings', 'Wrapping filling in a large flour tortilla', 'Spreading filling on flattened bread cut lengthwise and rolling it up like a jelly roll', 'Cutting bread into small fancy shapes for tea service', 'C', 1, 5, '2026-04-16 13:51:25'),
(97, 10, 'What is the \"4-40-140\" rule in sandwich storage safety?', 'Sandwiches should be 4 inches wide, 40 grams in weight, and stored at 140°F', 'Use 4 ingredients, store for 40 minutes, serve at 140°F', 'Perishable foods should spend no more than 4 hours at a temperature between 40°F and 140°F', 'Refrigerate at 4°C, freeze at -40°C, cook at 140°C', 'C', 1, 6, '2026-04-16 13:51:25'),
(98, 10, 'Which bread type is described as \"stronger tasting than white and whole wheat\" with a \"heavy and hearty flavor\"?', 'Focaccia', 'Pita', 'Rye bread', 'Lavash', 'C', 1, 7, '2026-04-16 13:51:25'),
(99, 10, 'According to the module, how should sandwich quarters be arranged on a platter for attractive presentation?', 'Flat on the tray with crust facing the viewer', 'With the cut edge of the sandwich pointing up at the viewer', 'Stacked on top of each other to save space', 'Wrapped individually and placed in rows', 'B', 1, 8, '2026-04-16 13:51:25'),
(100, 10, 'What equipment is described as a \"small broiler used primarily for browning or glazing the tops of sandwiches\"?', 'Griddle', 'Salamander', 'Microwave oven', 'Bread toaster', 'B', 1, 9, '2026-04-16 13:51:25'),
(101, 10, 'What is the correct storage temperature recommendation for sandwiches after packing?', '0.5°C (chill temperature)', '10°C (cool room temperature)', 'Room temperature (20-25°C)', '-18°C (freezer temperature)', 'A', 1, 10, '2026-04-16 13:51:25'),
(102, 11, 'Which type of sugar is best suited for making meringues because it dissolves more easily?', 'Granulated sugar', 'Confectioner\'s sugar', 'Brown sugar', 'Castor sugar', 'D', 1, 1, '2026-04-16 13:55:07'),
(103, 11, 'What are the characteristics of a good baked custard?', 'Soft, runny texture with strong egg flavor', 'Firmness of shape, smooth tender texture, rich and creamy consistency, excellent flavor', 'Crispy exterior and liquid interior', 'Light, airy, and crumbly', 'B', 1, 2, '2026-04-16 13:55:07'),
(104, 11, 'Which dessert classification is described as having \"a depth of two or three inches and topped with biscuit dough rather than pie crust\"?', 'Gelatin dessert', 'Fruit cobbler', 'Custard', 'Frozen soufflé', 'B', 1, 3, '2026-04-16 13:55:07'),
(105, 11, 'What is the correct procedure when combining egg yolks and sugar for vanilla custard sauce?', 'Let the sugar and egg yolks stand together for 10 minutes before mixing', 'Whip the mixture as soon as the sugar is added to prevent lumps', 'Add the sugar to cold egg yolks and heat immediately', 'Dissolve sugar in milk first, then add to egg yolks', 'B', 1, 4, '2026-04-16 13:55:07'),
(106, 11, 'Which of the following is a guideline for dessert plating and garnishing?', 'Always use fresh mint regardless of the dessert flavor', 'Place garnishes on the rim of the plate for easy access', 'Make garnishes edible and ensure they relate to the dessert on the plate', 'Use as many garnishes as possible to fill empty space', 'C', 1, 5, '2026-04-16 13:55:07'),
(107, 11, 'What is Pastry Cream (Crème Pâtissière) primarily used for?', 'Moistening cakes as a syrup', 'As a light pouring sauce for plated desserts', 'As cake and pastry fillings, cream pies, and puddings', 'As a frozen dessert base', 'C', 1, 6, '2026-04-16 13:55:07'),
(108, 11, 'Why should egg custards and desserts containing dairy products be cooled rapidly and refrigerated?', 'To prevent sugar crystallization', 'To maintain bright colors', 'Because they provide a good medium for bacteria to grow quickly', 'To prevent the formation of ice crystals', 'C', 1, 7, '2026-04-16 13:55:07'),
(109, 11, 'What is the function of a \"double boiler\" in dessert preparation?', 'To steam vegetables rapidly', 'To fry foods at a consistent temperature', 'To keep temperatures below boiling for delicate items like egg sauces and puddings', 'To bake multiple items simultaneously', 'C', 1, 8, '2026-04-16 13:55:07'),
(110, 11, 'Which of the following is the correct procedure if a vanilla custard sauce curdles?', 'Discard it immediately and start over', 'Add more sugar and continue heating', 'Immediately stir in 1 to 2 ounces of cold milk and blend at high speed', 'Strain it through a fine mesh sieve and add more egg yolks', 'C', 1, 9, '2026-04-16 13:55:07'),
(111, 11, 'According to the module, which frozen dessert contains milk, cream, sugar, flavorings, and sometimes eggs?', 'Sherbet', 'Ice cream', 'Ice', 'Frozen mousse', 'D', 1, 10, '2026-04-16 13:55:07'),
(112, 12, 'What is the main objective of food packaging?', 'To make the product more expensive looking', 'To reduce the weight of the product for shipping', 'To keep food in good condition until sold and consumed, and encourage customers to purchase', 'To comply with international color standards', 'C', 1, 1, '2026-04-16 14:00:39'),
(113, 12, 'Which packaging material is described as having the disadvantage of being \"heavier than many other materials, easy to fracture, and may cause hazards from cracks or fragments in food\"?', 'Plastic', 'Metal', 'Glass', 'Paper', 'C', 1, 2, '2026-04-16 14:00:39'),
(114, 12, 'What type of package directly contains the product and gets in direct contact with the goods?', 'Primary package', 'Secondary package', 'Tertiary package', 'Quaternary package', 'A', 1, 3, '2026-04-16 14:00:39'),
(115, 12, 'Which of the following is NOT a required element on a food label according to the module?', 'Name under which the product is sold', 'List of ingredients', 'Date of minimum durability', 'Suggested retail price', 'D', 1, 4, '2026-04-16 14:00:39'),
(116, 12, 'Why should newsprint (newspaper) NOT be allowed to come into direct contact with food?', 'It absorbs too much moisture from the food', 'It is too expensive for commercial use', 'The ink used is toxic', 'It provides no insulation', 'C', 1, 5, '2026-04-16 14:00:39'),
(117, 12, 'Which microorganism is described as \"the most important to the food processor\" and includes both harmless, beneficial, and disease-causing types?', 'Yeasts', 'Bacteria', 'Molds', 'Viruses', 'B', 1, 6, '2026-04-16 14:00:39'),
(118, 12, 'What is the advantage of foil packaging mentioned in the module?', 'It is the least expensive packaging method', 'It can be reused multiple times by consumers', 'It allows food to be sealed without losing residual moisture', 'It is transparent so consumers can see the product', 'C', 1, 7, '2026-04-16 14:00:39'),
(119, 12, 'Which packaging material is traditionally used for wrapping \"suman\" and other native delicacies?', 'Vegetable fiber baskets', 'Banana leaves', 'Earthenware pots', 'Metal cans', 'B', 1, 8, '2026-04-16 14:00:39'),
(120, 12, 'What happens to food products with higher moisture content regarding spoilage?', 'They have longer shelf life due to natural preservation', 'They have greater chances for microbial growth and chemical changes', 'They are immune to bacterial contamination', 'They can be stored safely at room temperature indefinitely', 'B', 1, 9, '2026-04-16 14:00:39'),
(121, 7, 'Which of the following is the correct distinction between cleaning and sanitizing?', 'Cleaning removes food soil; Sanitizing removes mineral deposits.', 'Cleaning is done with heat; Sanitizing is done with cold water.', 'Cleaning removes visible soil; Sanitizing reduces microorganisms to safe levels.', 'Cleaning is only for equipment; Sanitizing is only for cutting boards.', 'C', 1, 1, '2026-04-16 14:02:28'),
(122, 7, 'A food handler notices rust forming on a cast iron skillet. According to the module, what is the proper maintenance procedure?', 'Soak it overnight in detergent to loosen the rust.', 'Scrub with steel wool and store it wet to prevent cracking.', 'Rub it with unsalted salad oil or shortening and dry it completely.', 'Place it in the dishwasher on the \"Pots and Pans\" cycle.', 'C', 1, 2, '2026-04-16 14:02:28'),
(123, 7, 'Which cleaning agent is specifically formulated as \"alkaline-based\" to dissolve grease burned onto ovens and grills?', 'Abrasives', 'Acid Cleaners', 'Solvent Cleaners / Degreasers', 'Detergents', 'C', 1, 3, '2026-04-16 14:02:28'),
(124, 7, 'In the manual dishwashing procedure, what is the correct stacking order of dishes to the right of the sink?', 'Chinaware, Silverware, Glassware, Utensils', 'Glassware, Silverware, Chinaware, Utensils', 'Pots and Pans first, then Glassware', 'Heaviest items first, regardless of material', 'B', 1, 4, '2026-04-16 14:02:28'),
(125, 7, 'According to the module, what is the minimum temperature required for hot water sanitizing in the third compartment of a three-compartment sink?', '165°F (74°C)', '180°F (82°C)', '171°F (77°C)', '212°F (100°C)', 'C', 1, 5, '2026-04-16 14:02:28'),
(126, 7, 'A plastic cutting board has deep stains from slicing carrots. The module recommends leaving a paste of water and which substance on the stain for 24 hours?', 'Bleach', 'Baking Soda', 'Vinegar', 'Salt', 'D', 1, 6, '2026-04-16 14:02:28'),
(127, 7, 'Which characteristic is a DISADVANTAGE of using Chlorine as a chemical sanitizer?', 'It is not affected by hard water.', 'It is highly effective on a wide variety of bacteria.', 'It is corrosive to metal surfaces and irritating to skin.', 'It is generally inexpensive.', 'C', 1, 7, '2026-04-16 14:02:28'),
(128, 7, 'For proper storage of cleaned kitchen utensils in an open cabinet below the working top level, how should cups and bowls be positioned?', 'Stacked upright to allow air circulation.', 'Inverted (upside down).', 'Covered with a clean linen cloth only.', 'Stored horizontally to prevent chipping.', 'B', 1, 8, '2026-04-16 14:02:28'),
(129, 7, 'You are cleaning a microwave after a food explosion. Which type of cleaning agent should be avoided on food-contact surfaces because it leaves an \"unsafe residue\"?', 'Mild Detergent', 'Glass Cleaner (not labeled for food surfaces)', 'Vinegar Solution', 'Baking Soda Paste', 'B', 1, 9, '2026-04-16 14:02:28'),
(130, 7, 'What is the correct ratio for a sanitizing solution using liquid chlorine bleach for a cutting board?', '1 cup Bleach to 1 quart Water', '1 teaspoon Bleach to 1 quart Water', '1 part Bleach to 1 part Water', '1 tablespoon Bleach to 1 gallon Water', 'B', 1, 10, '2026-04-16 14:02:28'),
(135, 13, 'ANSWER IS A', 'A', 'B', 'C', 'D', 'A', 1, 1, '2026-04-16 14:06:25'),
(136, 13, 'ANSWER IS B', 'A', 'B', 'C', 'D', 'B', 1, 2, '2026-04-16 14:06:26'),
(137, 13, 'ANSWER IS C', 'A', 'B', 'C', 'D', 'C', 1, 3, '2026-04-16 14:06:26'),
(138, 13, 'ANSWER IS D', 'A', 'B', 'C', 'D', 'A', 1, 4, '2026-04-16 14:06:26');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_replied` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `proponent_id` int(11) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `total_pages` int(11) DEFAULT 0 COMMENT 'Total pages in course PDF',
  `file_video` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `summary` varchar(2500) DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `proponent_id`, `file_pdf`, `total_pages`, `file_video`, `created_at`, `updated_at`, `expires_at`, `is_active`, `summary`, `edited_at`) VALUES
(2, 'Training Regulations', 'Training RegulationsTraining Regulations', 'b3d2a09b4be0d731.png', 1, '029ef61120c5415b.pdf', 95, NULL, '2026-03-10 16:27:40', NULL, NULL, 1, 'Training RegulationsTraining RegulationsTraining Regulations', NULL),
(15, 'Preparing Poultry and Game Dishes', 'Introduction to Poultry and Game Meats and Fabrication', 'bd88da421e18e1ae.jpg', 1, 'c42fe4f19a11c402.pdf', 144, NULL, '2026-03-27 03:36:48', NULL, NULL, 1, 'At the end of this lesson, you should be able to:\r\n\r\nIdentify the primal and sub-primal cuts of poultry meat;\r\nHandle poultry and game meat safely,\r\nPerform basic techniques for preparing poultry and game meat,\r\nIdentify the primal and sub-primal cuts of poultry meat;\r\nHandle poultry and game meat safely; and\r\nPerform basic techniques for preparing poultry and game meat.', NULL),
(16, 'Fundamentals of Professional Cookery', 'Introduction and Basics to Professional Cookery', 'adc209404c6de5a0.jpg', 4, 'cb941e9dbb492d24.pdf', 0, NULL, '2026-03-27 03:48:15', NULL, NULL, 1, 'At the end of this unit, you should be able to:\r\n\r\nIdentify the organizational structure inside the kitchen;\r\nDistinguish the importance of the roles of the kitchen staff;\r\nEnumerate several duties and responsibilities of the kitchen staff;\r\nIdentify professional work habits observed in the kitchen;\r\nIdentify common kitchen tools, utensils, and equipment in the kitchen;\r\nConvert kitchen measurements;\r\nPractice food and occupational safety procedures;\r\nDemonstrate basic knife skills; and\r\nDefine basic and foreign culinary terms commonly used in the kitchen.', NULL),
(17, 'Preparing Appetizers and Hors d\'oeuvres', 'Introduction to Appetizers and Hors d\'oeuvres', '0fae4ff18d1dd962.jpg', 4, 'ee375b60c4d2aa69.pdf', 12, NULL, '2026-03-27 03:50:59', NULL, NULL, 1, 'At the end of this module, you should be able to:\r\n\r\nIdentify the different types of appetizers and hors d’oeuvres;\r\nDetermine the quality of ingredients for preparing appetizers and hors d’oeuvres;\r\nHandle appetizer and hors d’oeuvres ingredients safely;\r\nPerform personal safety procedures in the kitchen;\r\nPrepare common types of appetizers properly;\r\nPlate and serve common appetizer dishes according to standards; and\r\nIdentify the current trend in plating appetizer dishes.', NULL),
(18, 'Preparing Egg, Vegetable and Farinaceous Dishes', 'Preparing Egg, Vegetable and Farinaceous Dishes', 'a3b2b8598595055e.jpg', 4, 'df947da3e2357f6e.pdf', 8, NULL, '2026-03-27 03:55:08', NULL, NULL, 1, 'At the end of this unit, you should be able to:\r\n\r\nIdentify the components of an egg;\r\nDetermine the desirable qualities of eggs;\r\nHandle and store fresh eggs properly;\r\nPrepare eggs using various cooking methods;\r\nDetermine the types and characteristics of vegetables;\r\nIdentify the qualities of vegetables;\r\nPrepare vegetable dishes;\r\nPerform procedures for controlling changes in the quality of vegetables;\r\nIdentify the different farinaceous products;\r\nHandle and store farinaceous products safely; and\r\nPrepare the following farinaceous products using various cooking techniques.', NULL),
(19, 'Preparing Meat Dishes', 'Introduction to Meat and Meat Fabrication', '97337cb6b4a4d95e.jpg', 4, '1dadc6ea4344caac.pdf', 144, NULL, '2026-03-27 04:01:56', NULL, NULL, 1, 'At the end of this unit, you should be able to:\r\n\r\nIdentify the composition and structure of meat;\r\nDetermine the desirable qualities of meat; \r\nIdentify the quality grade of different types of meat;\r\nAt the end of this unit, you should be able to:\r\n\r\nPerform proper procedures for handling meat;\r\nDetermine the primal and sub-primal cuts of meat;\r\nDemonstrate the basic techniques for preparing meat.', NULL),
(20, 'Preparing Salads and Salad Dressings', 'Introduction to Salads and Salad Dressings', 'a19306834e0441df.jpg', 4, '466eca49aa623f43.pdf', 36, NULL, '2026-03-27 04:05:34', NULL, NULL, 1, 'At the end of this module, you should be able to:\r\n\r\nDefine salads and dressings;\r\nDetermine the components of a salad;\r\nIdentify common salad ingredients;\r\nObserve personal sanitation and safety measures in the kitchen;\r\nHandle salad ingredients properly and safely;\r\nPrepare salad dressings made of oil and vinegar, mayonnaise and cream;\r\nPrepare different types and variations of salads; and\r\nObserve the guidelines for plating salads.', NULL),
(21, 'Preparing Sandwiches', 'Introduction to Sandwiches with Preaparing and Plating', 'ef71fae0a4de65c7.jpg', 3, 'e0609139d72a66f8.pdf', 29, NULL, '2026-03-27 04:16:02', '2026-03-27 10:59:05', '2026-04-11', 1, 'At the end of this module, you should be able to:\r\n\r\nDefine what is a sandwich;\r\nDetermine the types of sandwiches and the components of a sandwich;\r\nIdentify the common tool and equipment used in preparing sandwiches;\r\nPerform and apply the basic techniques in preparing sandwiches;\r\nPrepare a variety of sandwich types;\r\nPlate the different types of sandwiches; and\r\nPerform the proper procedures for holding sandwiches.', NULL),
(22, 'Preparing Seafood Dishes', 'Introduction to Seafood and Seafood Fabrication', 'a1ca8eb616102bc5.jpg', 3, 'b8f3632cc7e7768a.pdf', 8, NULL, '2026-03-27 04:18:18', NULL, NULL, 1, 'At the end of this unit, you should be able to:\r\n\r\nIdentify the different parts of a fish;\r\nDetermine the types of fish;\r\nIdentify the types of shellfish;\r\nPerform and observe measures for the safe handling of seafood;\r\nDemonstrate proper procedures for preparing fish and shellfish for cooking; and\r\nUse different cooking methods in preparing seafood dishes.', NULL),
(23, 'CHAPTER I', 'KITCHEN PREPARATION, MAINTENANCE, AND TOOL HANDLING', '1641be35f4d9cbb2.jpg', 42, '5c34df5350b25333.pdf', 63, NULL, '2026-04-16 01:54:28', NULL, NULL, 1, 'Learning Outcome 1\r\nClean and Maintain Kitchen Tools, Equipment \r\nIncluding Kitchen Premises \r\n\r\nLearning Outcome 2 \r\nClean and Sanitize Kitchen Tool and Equipment \r\n\r\nLearning Outcome 3 \r\nClean and Sanitize Kitchen Premises ', NULL),
(24, 'CHAPTER II', 'APPETIZERS', '83468d4d695555f4.jpg', 42, '0d70333319a4c843.pdf', 47, NULL, '2026-04-16 01:56:21', NULL, NULL, 1, 'Lesson 1  \r\nPrepare Appetizers \r\n\r\nLearning Outcome 2 \r\nPerform Mise en place\r\n \r\nLearning Outcome 3  \r\nPrepare a Range of Appetizers \r\n\r\nLearning Outcome 4  \r\nPresent a Range of Appetizers\r\n \r\nLearning Outcome 5\r\nStore Appetizer ', NULL),
(25, 'CHAPTER III', 'SALAD AND DRESSINGS', 'b268c9663ce647e3.jpg', 42, 'dd23eeb6089098af.pdf', 58, NULL, '2026-04-16 01:58:23', NULL, NULL, 1, 'Lesson 1  \r\nPrepare Salad and Dressing\r\n\r\nLearning Outcome 1 \r\nPerform Mise en place \r\n\r\nLearning Outcome 2 \r\nPrepare a Variety of Salad and Dressings\r\n \r\nLearning Outcome 3  \r\nPresent a Variety of Salad and Dressings\r\n \r\nLearning Outcome 4  \r\nStore Salads and Dressing', NULL),
(26, 'CHAPTER IV', 'SANDWICHES', 'fea06c32dd3aa720.jpg', 42, 'b2f524e58a1ec321.pdf', 60, NULL, '2026-04-16 04:45:50', '2026-04-16 04:46:11', NULL, 1, 'Lesson 1 \r\nPrepare Sandwiches \r\n\r\nLearning Outcome 1 \r\nPerform Mise en place\r\n \r\nLearning Outcome 2 \r\nPrepare a Variety of Sandwiches \r\n\r\nLearning Outcome 3 \r\nPresent a Variety of Sandwiches \r\n\r\nLearning Outcome 4 \r\nStoring Sandwiches', NULL),
(27, 'CHAPTER V - LESSON 1', 'DESSERTS', '1a82835946582159.jpg', 42, 'dcec9d0093c6c532.pdf', 63, NULL, '2026-04-16 07:18:33', NULL, NULL, 1, 'Lesson 1\r\nPrepare Desserts\r\n \r\nLearning Outcome 1 \r\nPerform Mise en place\r\n \r\nLearning Outcome 2\r\nPrepare Desserts and Sweet Sauces\r\n \r\nLearning Outcome 3\r\nPlate/Present Desserts\r\n \r\nLearning Outcome 4\r\nStoring Desserts ', NULL),
(28, 'CHAPTER V - LESSON 2', 'FOOD PACKAGING', '67e8852227b93907.jpg', 42, '126b4e86abed8e27.pdf', 29, NULL, '2026-04-16 13:20:58', '2026-04-16 13:57:09', NULL, 1, ' \r\nLearning Outcome 1 \r\nSelect Packaging Materials\r\n \r\nLearning Outcome 2  \r\nPackage food items ', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_departments`
--

CREATE TABLE `course_departments` (
  `course_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `edit`
--

CREATE TABLE `edit` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `proponent_id` int(11) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `file_video` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `thumbnail` varchar(255) DEFAULT NULL,
  `summary` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `expired_at` date DEFAULT NULL,
  `progress` decimal(5,2) DEFAULT 0.00,
  `pages_viewed` int(11) DEFAULT 0 COMMENT 'Number of PDF pages viewed',
  `last_viewed_page` int(11) DEFAULT 0 COMMENT 'Last page viewed by student',
  `last_activity` timestamp NULL DEFAULT NULL COMMENT 'Last activity timestamp',
  `status` enum('ongoing','completed','expired') DEFAULT 'ongoing',
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `course_id`, `enrolled_at`, `completed_at`, `expired_at`, `progress`, `pages_viewed`, `last_viewed_page`, `last_activity`, `status`, `is_archived`) VALUES
(55, 27, 2, '2026-03-19 07:39:55', '2026-03-19 07:40:15', NULL, 100.00, 95, 0, NULL, 'completed', 1),
(64, 27, 22, '2026-03-27 09:55:58', NULL, NULL, 75.00, 6, 0, NULL, 'ongoing', 0),
(65, 27, 21, '2026-03-27 10:06:11', NULL, NULL, 3.00, 1, 0, NULL, 'ongoing', 0),
(66, 27, 20, '2026-03-27 10:06:17', NULL, NULL, 22.00, 8, 0, NULL, 'ongoing', 0),
(67, 31, 2, '2026-03-27 10:14:28', '2026-03-27 10:14:44', NULL, 100.00, 95, 0, NULL, 'completed', 1),
(68, 16, 23, '2026-04-16 11:28:03', NULL, NULL, 2.00, 1, 0, NULL, 'ongoing', 0),
(69, 20, 23, '2026-04-16 11:31:59', NULL, NULL, 2.00, 1, 0, NULL, 'ongoing', 0),
(70, 27, 23, '2026-04-16 11:36:00', NULL, NULL, 83.00, 52, 0, NULL, 'ongoing', 0),
(71, 29, 23, '2026-04-16 11:36:18', NULL, NULL, 2.00, 1, 0, NULL, 'ongoing', 0),
(72, 30, 23, '2026-04-16 11:36:47', NULL, NULL, 2.00, 1, 0, NULL, 'ongoing', 0),
(73, 31, 23, '2026-04-16 11:37:03', NULL, NULL, 2.00, 1, 0, NULL, 'ongoing', 0),
(74, 32, 23, '2026-04-16 11:37:16', NULL, NULL, 2.00, 1, 0, NULL, 'ongoing', 0),
(78, 43, 23, '2026-04-16 11:38:13', '2026-04-16 11:45:37', NULL, 100.00, 63, 0, NULL, 'completed', 0),
(79, 34, 23, '2026-04-16 11:46:41', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0);

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `body`, `created_by`, `created_at`, `is_published`) VALUES
(1, 'First News', 'First NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst News', 1, '2026-03-02 18:28:05', 1),
(6, 'COOKERY NC II FINAL EXAMINATION', 'FINAL EXAMIATION WILL BE CONDUCTED ON\r\n\r\nAPRIL 6 - 10, 2026\r\n\r\nPLEASE SETTLE YOUR ACCOUNT BALANCE AT THE REGISTRARS OFFICE\r\n\r\n', 3, '2026-03-17 01:45:00', 1),
(7, 'COOKERY PROJECT DEADLINE', '\r\nPLEASE PASS YOUR PROJECTS BEFORE \r\n\r\nAPRIL 9, 2026 \r\n\r\nNON COMPLIANCE WILL HAVE A FAIL GRADE\r\nNO EXEMPTIONS', 4, '2026-03-17 23:45:10', 1),
(12, 'Start of classess S.Y. 2026-2027', 'Courses will be available at June 12, 2026\r\nPlease be guided.', 42, '2026-04-16 01:13:02', 1);

-- --------------------------------------------------------

--
-- Table structure for table `news_read`
--

CREATE TABLE `news_read` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news_read`
--

INSERT INTO `news_read` (`id`, `user_id`, `news_id`, `read_at`) VALUES
(14, 1, 7, '2026-03-18 12:44:12'),
(16, 1, 6, '2026-03-19 00:19:33'),
(21, 1, 1, '2026-03-27 08:46:48');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 10 minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pdf_progress`
--

CREATE TABLE `pdf_progress` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `page_number` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pdf_progress`
--

INSERT INTO `pdf_progress` (`id`, `enrollment_id`, `page_number`, `viewed_at`) VALUES
(575, 55, 1, '2026-03-19 07:39:56'),
(576, 55, 2, '2026-03-19 07:39:59'),
(577, 55, 3, '2026-03-19 07:39:59'),
(578, 55, 4, '2026-03-19 07:40:00'),
(579, 55, 5, '2026-03-19 07:40:00'),
(580, 55, 6, '2026-03-19 07:40:01'),
(581, 55, 7, '2026-03-19 07:40:01'),
(582, 55, 8, '2026-03-19 07:40:01'),
(583, 55, 9, '2026-03-19 07:40:01'),
(584, 55, 10, '2026-03-19 07:40:01'),
(585, 55, 11, '2026-03-19 07:40:01'),
(586, 55, 12, '2026-03-19 07:40:01'),
(587, 55, 13, '2026-03-19 07:40:01'),
(588, 55, 14, '2026-03-19 07:40:01'),
(589, 55, 15, '2026-03-19 07:40:02'),
(590, 55, 16, '2026-03-19 07:40:02'),
(591, 55, 17, '2026-03-19 07:40:02'),
(592, 55, 18, '2026-03-19 07:40:02'),
(593, 55, 19, '2026-03-19 07:40:02'),
(594, 55, 20, '2026-03-19 07:40:02'),
(595, 55, 21, '2026-03-19 07:40:02'),
(596, 55, 22, '2026-03-19 07:40:02'),
(597, 55, 23, '2026-03-19 07:40:02'),
(598, 55, 24, '2026-03-19 07:40:03'),
(599, 55, 25, '2026-03-19 07:40:03'),
(600, 55, 26, '2026-03-19 07:40:03'),
(601, 55, 27, '2026-03-19 07:40:03'),
(602, 55, 28, '2026-03-19 07:40:03'),
(603, 55, 29, '2026-03-19 07:40:03'),
(604, 55, 30, '2026-03-19 07:40:03'),
(605, 55, 31, '2026-03-19 07:40:03'),
(606, 55, 32, '2026-03-19 07:40:03'),
(607, 55, 33, '2026-03-19 07:40:04'),
(608, 55, 34, '2026-03-19 07:40:04'),
(609, 55, 35, '2026-03-19 07:40:04'),
(610, 55, 36, '2026-03-19 07:40:05'),
(611, 55, 37, '2026-03-19 07:40:05'),
(612, 55, 38, '2026-03-19 07:40:05'),
(613, 55, 39, '2026-03-19 07:40:05'),
(614, 55, 40, '2026-03-19 07:40:06'),
(615, 55, 41, '2026-03-19 07:40:06'),
(616, 55, 42, '2026-03-19 07:40:06'),
(617, 55, 43, '2026-03-19 07:40:06'),
(618, 55, 44, '2026-03-19 07:40:06'),
(619, 55, 45, '2026-03-19 07:40:06'),
(620, 55, 46, '2026-03-19 07:40:06'),
(621, 55, 47, '2026-03-19 07:40:06'),
(622, 55, 48, '2026-03-19 07:40:06'),
(623, 55, 49, '2026-03-19 07:40:06'),
(624, 55, 50, '2026-03-19 07:40:07'),
(625, 55, 51, '2026-03-19 07:40:07'),
(626, 55, 52, '2026-03-19 07:40:07'),
(627, 55, 53, '2026-03-19 07:40:07'),
(628, 55, 54, '2026-03-19 07:40:07'),
(629, 55, 55, '2026-03-19 07:40:07'),
(630, 55, 56, '2026-03-19 07:40:08'),
(631, 55, 57, '2026-03-19 07:40:08'),
(632, 55, 58, '2026-03-19 07:40:08'),
(633, 55, 59, '2026-03-19 07:40:08'),
(634, 55, 60, '2026-03-19 07:40:08'),
(635, 55, 61, '2026-03-19 07:40:08'),
(636, 55, 62, '2026-03-19 07:40:08'),
(637, 55, 63, '2026-03-19 07:40:08'),
(638, 55, 64, '2026-03-19 07:40:09'),
(639, 55, 65, '2026-03-19 07:40:09'),
(640, 55, 66, '2026-03-19 07:40:09'),
(641, 55, 67, '2026-03-19 07:40:09'),
(642, 55, 68, '2026-03-19 07:40:09'),
(643, 55, 69, '2026-03-19 07:40:09'),
(644, 55, 70, '2026-03-19 07:40:10'),
(645, 55, 71, '2026-03-19 07:40:10'),
(646, 55, 72, '2026-03-19 07:40:10'),
(647, 55, 73, '2026-03-19 07:40:10'),
(648, 55, 74, '2026-03-19 07:40:10'),
(649, 55, 75, '2026-03-19 07:40:10'),
(650, 55, 76, '2026-03-19 07:40:10'),
(651, 55, 77, '2026-03-19 07:40:10'),
(652, 55, 78, '2026-03-19 07:40:11'),
(653, 55, 79, '2026-03-19 07:40:11'),
(654, 55, 80, '2026-03-19 07:40:11'),
(655, 55, 81, '2026-03-19 07:40:11'),
(656, 55, 82, '2026-03-19 07:40:11'),
(657, 55, 83, '2026-03-19 07:40:11'),
(658, 55, 84, '2026-03-19 07:40:11'),
(659, 55, 85, '2026-03-19 07:40:11'),
(660, 55, 86, '2026-03-19 07:40:11'),
(661, 55, 87, '2026-03-19 07:40:12'),
(662, 55, 88, '2026-03-19 07:40:12'),
(663, 55, 89, '2026-03-19 07:40:12'),
(664, 55, 90, '2026-03-19 07:40:12'),
(665, 55, 91, '2026-03-19 07:40:12'),
(666, 55, 92, '2026-03-19 07:40:12'),
(667, 55, 93, '2026-03-19 07:40:12'),
(668, 55, 94, '2026-03-19 07:40:12'),
(669, 55, 95, '2026-03-19 07:40:12'),
(758, 64, 1, '2026-03-27 09:56:00'),
(759, 64, 2, '2026-03-27 09:56:02'),
(760, 64, 3, '2026-03-27 09:56:04'),
(761, 64, 4, '2026-03-27 09:57:53'),
(762, 64, 5, '2026-03-27 09:57:54'),
(763, 64, 6, '2026-03-27 09:57:55'),
(764, 65, 1, '2026-03-27 10:06:12'),
(765, 66, 1, '2026-03-27 10:06:18'),
(766, 66, 2, '2026-03-27 10:06:26'),
(767, 66, 3, '2026-03-27 10:06:29'),
(768, 66, 4, '2026-03-27 10:06:29'),
(769, 66, 5, '2026-03-27 10:06:30'),
(770, 66, 6, '2026-03-27 10:06:31'),
(771, 66, 7, '2026-03-27 10:06:31'),
(772, 66, 8, '2026-03-27 10:06:32'),
(773, 67, 1, '2026-03-27 10:14:29'),
(774, 67, 2, '2026-03-27 10:14:31'),
(775, 67, 3, '2026-03-27 10:14:31'),
(776, 67, 4, '2026-03-27 10:14:32'),
(777, 67, 5, '2026-03-27 10:14:32'),
(778, 67, 6, '2026-03-27 10:14:32'),
(779, 67, 7, '2026-03-27 10:14:32'),
(780, 67, 8, '2026-03-27 10:14:33'),
(781, 67, 9, '2026-03-27 10:14:33'),
(782, 67, 10, '2026-03-27 10:14:33'),
(783, 67, 11, '2026-03-27 10:14:33'),
(784, 67, 12, '2026-03-27 10:14:33'),
(785, 67, 13, '2026-03-27 10:14:34'),
(786, 67, 14, '2026-03-27 10:14:35'),
(787, 67, 15, '2026-03-27 10:14:36'),
(788, 67, 16, '2026-03-27 10:14:36'),
(789, 67, 17, '2026-03-27 10:14:36'),
(790, 67, 18, '2026-03-27 10:14:36'),
(791, 67, 19, '2026-03-27 10:14:36'),
(792, 67, 20, '2026-03-27 10:14:36'),
(793, 67, 21, '2026-03-27 10:14:36'),
(794, 67, 22, '2026-03-27 10:14:36'),
(795, 67, 23, '2026-03-27 10:14:37'),
(796, 67, 24, '2026-03-27 10:14:37'),
(797, 67, 25, '2026-03-27 10:14:37'),
(798, 67, 26, '2026-03-27 10:14:37'),
(799, 67, 27, '2026-03-27 10:14:37'),
(800, 67, 28, '2026-03-27 10:14:37'),
(801, 67, 29, '2026-03-27 10:14:37'),
(802, 67, 30, '2026-03-27 10:14:37'),
(803, 67, 31, '2026-03-27 10:14:37'),
(804, 67, 32, '2026-03-27 10:14:37'),
(805, 67, 33, '2026-03-27 10:14:37'),
(806, 67, 34, '2026-03-27 10:14:37'),
(807, 67, 35, '2026-03-27 10:14:37'),
(808, 67, 36, '2026-03-27 10:14:37'),
(809, 67, 37, '2026-03-27 10:14:37'),
(810, 67, 38, '2026-03-27 10:14:37'),
(811, 67, 39, '2026-03-27 10:14:37'),
(812, 67, 40, '2026-03-27 10:14:37'),
(813, 67, 41, '2026-03-27 10:14:37'),
(814, 67, 42, '2026-03-27 10:14:37'),
(815, 67, 43, '2026-03-27 10:14:37'),
(816, 67, 44, '2026-03-27 10:14:37'),
(817, 67, 45, '2026-03-27 10:14:37'),
(818, 67, 46, '2026-03-27 10:14:37'),
(819, 67, 47, '2026-03-27 10:14:38'),
(820, 67, 48, '2026-03-27 10:14:38'),
(821, 67, 49, '2026-03-27 10:14:38'),
(822, 67, 50, '2026-03-27 10:14:38'),
(823, 67, 51, '2026-03-27 10:14:38'),
(824, 67, 52, '2026-03-27 10:14:38'),
(825, 67, 53, '2026-03-27 10:14:38'),
(826, 67, 54, '2026-03-27 10:14:38'),
(827, 67, 55, '2026-03-27 10:14:38'),
(828, 67, 56, '2026-03-27 10:14:38'),
(829, 67, 57, '2026-03-27 10:14:38'),
(830, 67, 58, '2026-03-27 10:14:38'),
(831, 67, 59, '2026-03-27 10:14:38'),
(832, 67, 60, '2026-03-27 10:14:38'),
(833, 67, 61, '2026-03-27 10:14:38'),
(834, 67, 62, '2026-03-27 10:14:38'),
(835, 67, 63, '2026-03-27 10:14:38'),
(836, 67, 64, '2026-03-27 10:14:38'),
(837, 67, 65, '2026-03-27 10:14:38'),
(838, 67, 66, '2026-03-27 10:14:38'),
(839, 67, 67, '2026-03-27 10:14:38'),
(840, 67, 68, '2026-03-27 10:14:39'),
(841, 67, 69, '2026-03-27 10:14:39'),
(842, 67, 70, '2026-03-27 10:14:39'),
(843, 67, 71, '2026-03-27 10:14:39'),
(844, 67, 72, '2026-03-27 10:14:39'),
(845, 67, 73, '2026-03-27 10:14:39'),
(846, 67, 74, '2026-03-27 10:14:39'),
(847, 67, 75, '2026-03-27 10:14:39'),
(848, 67, 76, '2026-03-27 10:14:39'),
(849, 67, 77, '2026-03-27 10:14:39'),
(850, 67, 78, '2026-03-27 10:14:39'),
(851, 67, 79, '2026-03-27 10:14:39'),
(852, 67, 80, '2026-03-27 10:14:39'),
(853, 67, 81, '2026-03-27 10:14:40'),
(854, 67, 82, '2026-03-27 10:14:40'),
(855, 67, 83, '2026-03-27 10:14:40'),
(856, 67, 84, '2026-03-27 10:14:40'),
(857, 67, 85, '2026-03-27 10:14:40'),
(858, 67, 86, '2026-03-27 10:14:40'),
(859, 67, 87, '2026-03-27 10:14:40'),
(860, 67, 88, '2026-03-27 10:14:40'),
(861, 67, 89, '2026-03-27 10:14:40'),
(862, 67, 90, '2026-03-27 10:14:40'),
(863, 67, 91, '2026-03-27 10:14:40'),
(864, 67, 92, '2026-03-27 10:14:41'),
(865, 67, 93, '2026-03-27 10:14:41'),
(866, 67, 94, '2026-03-27 10:14:41'),
(867, 67, 95, '2026-03-27 10:14:41'),
(868, 68, 1, '2026-04-16 11:28:04'),
(869, 69, 1, '2026-04-16 11:32:00'),
(870, 70, 1, '2026-04-16 11:36:01'),
(871, 71, 1, '2026-04-16 11:36:19'),
(872, 72, 1, '2026-04-16 11:36:47'),
(873, 73, 1, '2026-04-16 11:37:04'),
(874, 74, 1, '2026-04-16 11:37:17'),
(878, 78, 1, '2026-04-16 11:38:14'),
(879, 78, 2, '2026-04-16 11:38:18'),
(880, 78, 3, '2026-04-16 11:38:20'),
(881, 78, 4, '2026-04-16 11:38:21'),
(882, 78, 5, '2026-04-16 11:38:22'),
(883, 78, 6, '2026-04-16 11:38:23'),
(884, 78, 7, '2026-04-16 11:38:24'),
(885, 78, 8, '2026-04-16 11:38:24'),
(886, 78, 9, '2026-04-16 11:38:24'),
(887, 78, 10, '2026-04-16 11:38:24'),
(888, 78, 11, '2026-04-16 11:38:25'),
(889, 78, 12, '2026-04-16 11:38:25'),
(890, 78, 13, '2026-04-16 11:38:25'),
(891, 78, 14, '2026-04-16 11:38:26'),
(892, 78, 15, '2026-04-16 11:38:26'),
(893, 78, 16, '2026-04-16 11:38:27'),
(894, 78, 17, '2026-04-16 11:38:27'),
(895, 78, 18, '2026-04-16 11:38:27'),
(896, 78, 19, '2026-04-16 11:38:29'),
(897, 78, 20, '2026-04-16 11:38:29'),
(898, 78, 21, '2026-04-16 11:38:30'),
(899, 78, 22, '2026-04-16 11:38:30'),
(900, 78, 23, '2026-04-16 11:38:31'),
(901, 78, 24, '2026-04-16 11:38:31'),
(902, 78, 25, '2026-04-16 11:38:32'),
(903, 78, 26, '2026-04-16 11:38:32'),
(904, 78, 27, '2026-04-16 11:38:32'),
(905, 78, 28, '2026-04-16 11:38:33'),
(906, 78, 29, '2026-04-16 11:38:33'),
(907, 78, 30, '2026-04-16 11:38:34'),
(908, 78, 31, '2026-04-16 11:38:34'),
(909, 78, 32, '2026-04-16 11:38:34'),
(910, 78, 33, '2026-04-16 11:45:24'),
(911, 78, 34, '2026-04-16 11:45:25'),
(912, 78, 35, '2026-04-16 11:45:25'),
(913, 78, 36, '2026-04-16 11:45:25'),
(914, 78, 37, '2026-04-16 11:45:25'),
(915, 78, 38, '2026-04-16 11:45:26'),
(916, 78, 39, '2026-04-16 11:45:26'),
(917, 78, 40, '2026-04-16 11:45:26'),
(918, 78, 41, '2026-04-16 11:45:26'),
(919, 78, 42, '2026-04-16 11:45:27'),
(920, 78, 43, '2026-04-16 11:45:27'),
(921, 78, 44, '2026-04-16 11:45:27'),
(922, 78, 45, '2026-04-16 11:45:27'),
(923, 78, 46, '2026-04-16 11:45:28'),
(924, 78, 47, '2026-04-16 11:45:28'),
(925, 78, 48, '2026-04-16 11:45:28'),
(926, 78, 49, '2026-04-16 11:45:28'),
(927, 78, 50, '2026-04-16 11:45:28'),
(928, 78, 51, '2026-04-16 11:45:28'),
(929, 78, 52, '2026-04-16 11:45:29'),
(930, 78, 53, '2026-04-16 11:45:29'),
(931, 78, 54, '2026-04-16 11:45:29'),
(932, 78, 55, '2026-04-16 11:45:29'),
(933, 78, 56, '2026-04-16 11:45:30'),
(934, 78, 57, '2026-04-16 11:45:30'),
(935, 78, 58, '2026-04-16 11:45:31'),
(936, 78, 59, '2026-04-16 11:45:31'),
(937, 78, 60, '2026-04-16 11:45:31'),
(938, 78, 61, '2026-04-16 11:45:31'),
(939, 78, 62, '2026-04-16 11:45:31'),
(940, 78, 63, '2026-04-16 11:45:32'),
(941, 70, 2, '2026-04-16 11:51:41'),
(942, 70, 3, '2026-04-16 11:51:45'),
(943, 70, 4, '2026-04-16 11:51:51'),
(944, 70, 5, '2026-04-16 11:51:56'),
(945, 70, 6, '2026-04-16 11:52:00'),
(946, 70, 7, '2026-04-16 11:52:04'),
(947, 70, 8, '2026-04-16 11:52:07'),
(948, 70, 9, '2026-04-16 11:52:11'),
(949, 70, 10, '2026-04-16 11:52:15'),
(950, 70, 11, '2026-04-16 11:52:21'),
(951, 70, 12, '2026-04-16 11:52:25'),
(952, 70, 13, '2026-04-16 12:18:00'),
(953, 70, 14, '2026-04-16 12:18:02'),
(954, 70, 15, '2026-04-16 12:18:04'),
(955, 70, 16, '2026-04-16 12:18:06'),
(956, 70, 17, '2026-04-16 12:18:12'),
(957, 70, 18, '2026-04-16 12:18:25'),
(958, 70, 22, '2026-04-16 12:27:47'),
(959, 70, 23, '2026-04-16 12:27:49'),
(960, 70, 24, '2026-04-16 12:27:52'),
(961, 70, 25, '2026-04-16 12:27:55'),
(962, 70, 26, '2026-04-16 12:28:01'),
(963, 70, 27, '2026-04-16 12:28:04'),
(964, 70, 40, '2026-04-16 12:28:08'),
(965, 70, 39, '2026-04-16 12:28:11'),
(966, 70, 38, '2026-04-16 12:28:13'),
(967, 70, 37, '2026-04-16 12:28:15'),
(968, 70, 36, '2026-04-16 12:28:16'),
(969, 70, 35, '2026-04-16 12:28:17'),
(970, 70, 34, '2026-04-16 12:28:19'),
(971, 70, 33, '2026-04-16 12:28:19'),
(972, 70, 32, '2026-04-16 12:28:21'),
(973, 70, 31, '2026-04-16 12:28:23'),
(974, 70, 29, '2026-04-16 12:28:25'),
(975, 70, 28, '2026-04-16 12:28:26'),
(976, 70, 30, '2026-04-16 12:28:31'),
(977, 70, 41, '2026-04-16 12:28:39'),
(978, 70, 42, '2026-04-16 12:28:41'),
(979, 70, 43, '2026-04-16 12:28:44'),
(980, 70, 44, '2026-04-16 12:28:46'),
(981, 70, 45, '2026-04-16 12:28:47'),
(982, 70, 46, '2026-04-16 12:28:50'),
(983, 70, 47, '2026-04-16 12:28:51'),
(984, 70, 48, '2026-04-16 12:28:52'),
(985, 70, 49, '2026-04-16 12:28:55'),
(986, 70, 50, '2026-04-16 12:28:56'),
(987, 70, 51, '2026-04-16 12:28:58'),
(988, 70, 52, '2026-04-16 12:29:00'),
(989, 70, 53, '2026-04-16 12:29:02'),
(990, 70, 54, '2026-04-16 12:29:04'),
(991, 70, 55, '2026-04-16 12:29:05');

-- --------------------------------------------------------

--
-- Table structure for table `time_logs`
--

CREATE TABLE `time_logs` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `start_ts` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `end_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `seconds` int(11) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fname` varchar(100) DEFAULT NULL,
  `lname` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('admin','proponent','user','superadmin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed') DEFAULT 'confirmed',
  `message_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `departments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fname`, `lname`, `email`, `role`, `created_at`, `updated_at`, `is_verified`, `otp_code`, `otp_expires_at`, `status`, `message_notifications`, `email_notifications`, `departments`) VALUES
(1, 'cooklabs1225', '$2y$10$LbuURKSYkxEB6V2qJE9jS.AQfv6cQObJZ4/mlQtbDu/A8dp7P1Z9K', 'Super Admin', 'User', 'superadmin@cooklabs.com', 'superadmin', '2026-03-03 01:10:36', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(3, 'admin', '$2y$10$af/ikZHii0fV18bHP5uFeu1Ts/MqKnZujyLeXjk7u.LGETD49ANA.', 'Admin', 'User', 'admin@cooklabs.com', 'admin', '2026-03-03 01:24:24', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(4, 'instructor', '$2y$10$JxbaFCY4HB8bwFdSL4IWgOSJhYAeEhiBvmDBMd4MsjUFrJE8LgYje', 'Instructor', 'User', 'instructor@cooklabs.com', 'proponent', '2026-03-03 01:26:06', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(16, 'kookysaurus', '$2y$10$.1ND70S0HRhZi/1reShwaefi0rE3qxvuuCxg2XEg.YdPppV.nqfly', 'Kooky Lyann', 'Arabia', 'kookyarabia06@gmail.com', 'user', '2026-03-17 08:26:19', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(20, 'marga', '$2y$10$Rlx1T3J.GG6GIKeIgXgNEepNnLwD8oiWgL0W7a1UzAukJzejwW.Ca', 'Margarette', 'Duazo', 'duazomargarette@gmail.com', 'user', '2026-03-18 08:20:04', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(27, 'user', '$2y$10$BR0e7a/wDJpphj9rqvc2LO5tPF9RAmpGE0N07MyCPDbYYFfcz2aXy', 'user', 'user', 'user@test.com', 'user', '2026-03-19 07:35:07', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(29, 'mika', '$2y$10$4hFqLzwI5kPMBwjwNnKOhOQMQTW6e8nNRuQGThJsUaAh6vPFQJmXa', 'Mikaella Rosalia', 'Torre', 'mikaellayap23@gmail.com', 'user', '2026-03-26 08:57:43', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(30, 'test1', '$2y$10$/QM2ZqaJAmjQV7I43bZ49OqM1g/E2b27GYqH.3Mo3uE4eUN415Ohe', 'Student', 'Test', 'fgithub455@gmail.com', 'user', '2026-03-26 08:58:58', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(31, 'test2', '$2y$10$c6GH6SkSv4hYB5CNKMmnOebN5eyfNqeIRmsIZVUYTdAm81TguLqPm', 'Student 1', 'Test', 'hoelykyeow@gmail.com', 'user', '2026-03-26 09:00:22', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(32, 'test3', '$2y$10$4wjfsfXo5tsMiXGaMFX2qOPMj04a24fXQ/CyEP5bdHDgrziXnchWy', 'Student 2', 'Test', 'starrynyx61@gmail.com', 'user', '2026-03-26 09:01:02', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(33, 'jahn', '$2y$10$yDq1XQUeGRPd9SpLw5wzieIp.IXv60qSjLxfJDCs0X/B/Dnb8JxRC', 'Jahn Nickole', 'Verdadero', 'Jaahnverdadero@gmail.com', 'user', '2026-03-26 09:01:45', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(34, 'leeyan', '$2y$10$Ihcx/YXErVJFqMULscr95O8yGcUlpX4lTGQrvbettekMoaCLsdtwG', 'Leeyan', 'Arabia', 'leeyansaurus@gmail.com', 'user', '2026-03-26 09:02:30', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(35, 'test4', '$2y$10$OxzM11U0i6iUNHFvbktZ1u3Ld2f4uwxPi8XDsDVNXyhoabYTthR3G', 'Student 3', 'Test', 'kookylyannarabia@gmail.com', 'user', '2026-03-26 09:03:04', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(36, 'pandinguser', '$2y$10$DPlB28BPUWIEfIWuQs9OJuPnVql/GVKvY0FG0ZS4LG1s3vZnufASu', 'Pending', 'User', 'gplankton1@gmail.com', 'user', '2026-03-27 10:28:29', NULL, 0, NULL, NULL, 'pending', 1, 1, NULL),
(42, 'instructor2', '$2y$10$pUjfAPRRNAVet309.MfYluvmwT16i0atJlwobrUEUEDj9R0U9QUg2', 'Renz Aaron', 'Mendiola', 'chikinboi1225@gmail.com', 'proponent', '2026-04-16 01:36:44', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(43, 'renz', '$2y$10$SB1C0olNP48CMxq889r7v.jvZL0ZUo/OdFrOLmZ..aL..K1U2ONK6', 'Renz', 'Mendiola', 'ramendiola418@gmail.com', 'user', '2026-04-16 02:49:39', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_assessments_course` (`course_id`);

--
-- Indexes for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_answers_attempt` (`attempt_id`);

--
-- Indexes for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_assessment` (`assessment_id`),
  ADD KEY `idx_attempts_user` (`user_id`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proponent_id` (`proponent_id`);

--
-- Indexes for table `course_departments`
--
ALTER TABLE `course_departments`
  ADD PRIMARY KEY (`course_id`,`department_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `edit`
--
ALTER TABLE `edit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proponent_id` (`proponent_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_user_course` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `news_read`
--
ALTER TABLE `news_read`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_news` (`user_id`,`news_id`),
  ADD KEY `news_id` (`news_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_view` (`enrollment_id`,`page_number`),
  ADD KEY `idx_pdf_progress_enrollment` (`enrollment_id`);

--
-- Indexes for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `edit`
--
ALTER TABLE `edit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `news_read`
--
ALTER TABLE `news_read`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=992;

--
-- AUTO_INCREMENT for table `time_logs`
--
ALTER TABLE `time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD CONSTRAINT `assessment_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD CONSTRAINT `assessment_attempts_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`proponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `edit`
--
ALTER TABLE `edit`
  ADD CONSTRAINT `edit_ibfk_1` FOREIGN KEY (`proponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `news_read`
--
ALTER TABLE `news_read`
  ADD CONSTRAINT `news_read_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `news_read_ibfk_2` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  ADD CONSTRAINT `pdf_progress_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD CONSTRAINT `time_logs_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
