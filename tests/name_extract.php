<?php
// Errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test
require_once '../libs/FullnameParser.php';

// Test name extrect
$name1 = new FullnameParser("Mr. Martin Picha");
$name1 = $name1->getNamePartials();
$name2 = new FullnameParser("Martin Picha");
$name2 = $name2->getNamePartials();
$name3 = new FullnameParser("Martin");
$name3 = $name3->getNamePartials();


assert(
	'Martin' == $name1->fname
);

assert(
	'Picha' == $name1->lname
);

assert(
	'Martin' == $name1->fname
);
