<?php
// =========================================
// MISUKI DETAILED WEEKLY SCHEDULE
// Super detailed day-by-day schedule
// =========================================

function getMisukiWeeklySchedule() {
    return [
        'monday' => [
            ['time' => '05:30', 'activity' => 'Waking up', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '05:35', 'activity' => 'Getting out of bed', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '05:40', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '05:45', 'activity' => 'Showering', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '06:00', 'activity' => 'Getting dressed', 'emoji' => '👔', 'type' => 'personal'],
            ['time' => '06:10', 'activity' => 'Preparing breakfast', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '06:15', 'activity' => 'Eating breakfast', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '06:25', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '06:30', 'activity' => 'Getting ready to leave', 'emoji' => '🎒', 'type' => 'personal'],
            ['time' => '06:40', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '06:50', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '07:00', 'activity' => 'Train ride to university', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '07:20', 'activity' => 'Arrived at university', 'emoji' => '🏫', 'type' => 'commute'],
            ['time' => '07:25', 'activity' => 'Walking to class building', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '07:30', 'activity' => 'Waiting in classroom', 'emoji' => '📚', 'type' => 'university'],
            ['time' => '07:45', 'activity' => 'Organic Chemistry lecture', 'emoji' => '🧪', 'type' => 'class'],
            ['time' => '09:30', 'activity' => 'Class break', 'emoji' => '☕', 'type' => 'break'],
            ['time' => '09:45', 'activity' => 'Walking to next class', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '10:00', 'activity' => 'Physical Chemistry lecture', 'emoji' => '⚗️', 'type' => 'class'],
            ['time' => '11:45', 'activity' => 'Class ends', 'emoji' => '✅', 'type' => 'university'],
            ['time' => '12:00', 'activity' => 'Having lunch at campus', 'emoji' => '🍱', 'type' => 'personal'],
            ['time' => '12:45', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '13:00', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '13:10', 'activity' => 'Train ride home', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '13:30', 'activity' => 'Walking home from station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '13:45', 'activity' => 'Arriving home', 'emoji' => '🏠', 'type' => 'personal'],
            ['time' => '13:50', 'activity' => 'Changing clothes', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '14:00', 'activity' => 'Resting', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '15:00', 'activity' => 'Starting homework', 'emoji' => '📖', 'type' => 'studying'],
            ['time' => '17:00', 'activity' => 'Taking a study break', 'emoji' => '☕', 'type' => 'break'],
            ['time' => '17:30', 'activity' => 'Continuing homework', 'emoji' => '✏️', 'type' => 'studying'],
            ['time' => '19:00', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '19:30', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '20:15', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '20:30', 'activity' => 'Free time', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '22:00', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '22:30', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '23:00', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ],
        
        'tuesday' => [
            ['time' => '06:00', 'activity' => 'Waking up', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '06:05', 'activity' => 'Getting out of bed', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '06:10', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '06:15', 'activity' => 'Showering', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '06:30', 'activity' => 'Getting dressed', 'emoji' => '👔', 'type' => 'personal'],
            ['time' => '06:40', 'activity' => 'Preparing breakfast', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '06:45', 'activity' => 'Eating breakfast', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '06:55', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '07:00', 'activity' => 'Getting ready to leave', 'emoji' => '🎒', 'type' => 'personal'],
            ['time' => '07:10', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '07:20', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '07:30', 'activity' => 'Train ride to university', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '07:50', 'activity' => 'Arrived at university', 'emoji' => '🏫', 'type' => 'commute'],
            ['time' => '07:55', 'activity' => 'Walking to class building', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '08:00', 'activity' => 'Waiting in classroom', 'emoji' => '📚', 'type' => 'university'],
            ['time' => '08:15', 'activity' => 'Analytical Chemistry lecture', 'emoji' => '🧪', 'type' => 'class'],
            ['time' => '10:00', 'activity' => 'Class break', 'emoji' => '☕', 'type' => 'break'],
            ['time' => '10:15', 'activity' => 'Walking to next class', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '10:30', 'activity' => 'Chemistry Lab', 'emoji' => '🔬', 'type' => 'class'],
            ['time' => '12:30', 'activity' => 'Lab ends', 'emoji' => '✅', 'type' => 'university'],
            ['time' => '12:45', 'activity' => 'Having lunch at campus', 'emoji' => '🍱', 'type' => 'personal'],
            ['time' => '13:30', 'activity' => 'Walking to next class', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '13:45', 'activity' => 'Biochemistry lecture', 'emoji' => '🧬', 'type' => 'class'],
            ['time' => '15:30', 'activity' => 'Class ends', 'emoji' => '✅', 'type' => 'university'],
            ['time' => '15:45', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '16:00', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '16:10', 'activity' => 'Train ride home', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '16:30', 'activity' => 'Walking home from station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '16:45', 'activity' => 'Arriving home', 'emoji' => '🏠', 'type' => 'personal'],
            ['time' => '16:50', 'activity' => 'Changing clothes', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '17:00', 'activity' => 'Resting', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '18:00', 'activity' => 'Writing lab report', 'emoji' => '📝', 'type' => 'studying'],
            ['time' => '19:30', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '20:00', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '20:45', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '21:00', 'activity' => 'Free time', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '22:30', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '23:00', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '23:30', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ],
        
        'wednesday' => [
            ['time' => '07:00', 'activity' => 'Waking up', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '07:10', 'activity' => 'Getting out of bed slowly', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '07:20', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '07:25', 'activity' => 'Showering', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '07:40', 'activity' => 'Getting dressed casually', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '07:50', 'activity' => 'Preparing breakfast', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '08:00', 'activity' => 'Eating breakfast', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '08:30', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '08:45', 'activity' => 'Free time at home', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '10:00', 'activity' => 'Starting homework', 'emoji' => '📖', 'type' => 'studying'],
            ['time' => '12:00', 'activity' => 'Preparing lunch', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '12:30', 'activity' => 'Eating lunch', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '13:00', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '13:15', 'activity' => 'Taking a break', 'emoji' => '☕', 'type' => 'break'],
            ['time' => '14:00', 'activity' => 'Continuing homework', 'emoji' => '✏️', 'type' => 'studying'],
            ['time' => '16:00', 'activity' => 'Study break', 'emoji' => '😌', 'type' => 'break'],
            ['time' => '16:30', 'activity' => 'Reading textbook', 'emoji' => '📚', 'type' => 'studying'],
            ['time' => '18:00', 'activity' => 'Free time', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '19:00', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '19:30', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '20:15', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '20:30', 'activity' => 'Free time relaxing', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '22:00', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '22:30', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '23:00', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ],
        
        'thursday' => [
            ['time' => '05:30', 'activity' => 'Waking up', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '05:35', 'activity' => 'Getting out of bed', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '05:40', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '05:45', 'activity' => 'Showering', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '06:00', 'activity' => 'Getting dressed', 'emoji' => '👔', 'type' => 'personal'],
            ['time' => '06:10', 'activity' => 'Preparing breakfast', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '06:15', 'activity' => 'Eating breakfast', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '06:25', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '06:30', 'activity' => 'Getting ready to leave', 'emoji' => '🎒', 'type' => 'personal'],
            ['time' => '06:40', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '06:50', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '07:00', 'activity' => 'Train ride to university', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '07:20', 'activity' => 'Arrived at university', 'emoji' => '🏫', 'type' => 'commute'],
            ['time' => '07:25', 'activity' => 'Walking to class building', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '07:30', 'activity' => 'Waiting in classroom', 'emoji' => '📚', 'type' => 'university'],
            ['time' => '07:45', 'activity' => 'Inorganic Chemistry lecture', 'emoji' => '⚛️', 'type' => 'class'],
            ['time' => '09:30', 'activity' => 'Class ends', 'emoji' => '✅', 'type' => 'university'],
            ['time' => '09:45', 'activity' => 'Having early lunch', 'emoji' => '🍱', 'type' => 'personal'],
            ['time' => '10:30', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '10:45', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '11:00', 'activity' => 'Train ride home', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '11:20', 'activity' => 'Walking home from station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '11:35', 'activity' => 'Arriving home', 'emoji' => '🏠', 'type' => 'personal'],
            ['time' => '11:40', 'activity' => 'Changing clothes', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '11:50', 'activity' => 'Free time', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '13:00', 'activity' => 'Starting homework', 'emoji' => '📖', 'type' => 'studying'],
            ['time' => '15:00', 'activity' => 'Study break', 'emoji' => '☕', 'type' => 'break'],
            ['time' => '15:30', 'activity' => 'Continuing homework', 'emoji' => '✏️', 'type' => 'studying'],
            ['time' => '17:30', 'activity' => 'Free time', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '18:30', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '19:00', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '19:45', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '20:00', 'activity' => 'Free time relaxing', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '22:00', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '22:30', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '23:00', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ],
        
        'friday' => [
            ['time' => '06:00', 'activity' => 'Waking up', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '06:05', 'activity' => 'Getting out of bed', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '06:10', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '06:15', 'activity' => 'Showering', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '06:30', 'activity' => 'Getting dressed', 'emoji' => '👔', 'type' => 'personal'],
            ['time' => '06:40', 'activity' => 'Preparing breakfast', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '06:45', 'activity' => 'Eating breakfast', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '06:55', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '07:00', 'activity' => 'Getting ready to leave', 'emoji' => '🎒', 'type' => 'personal'],
            ['time' => '07:10', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '07:20', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '07:30', 'activity' => 'Train ride to university', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '07:50', 'activity' => 'Arrived at university', 'emoji' => '🏫', 'type' => 'commute'],
            ['time' => '07:55', 'activity' => 'Walking to class building', 'emoji' => '🚶‍♀️', 'type' => 'university'],
            ['time' => '08:00', 'activity' => 'Waiting in classroom', 'emoji' => '📚', 'type' => 'university'],
            ['time' => '08:15', 'activity' => 'Mathematics for Chemistry lecture', 'emoji' => '📐', 'type' => 'class'],
            ['time' => '10:00', 'activity' => 'Class ends', 'emoji' => '✅', 'type' => 'university'],
            ['time' => '10:15', 'activity' => 'Having lunch at campus', 'emoji' => '🍱', 'type' => 'personal'],
            ['time' => '11:00', 'activity' => 'Walking to train station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '11:15', 'activity' => 'Waiting for train', 'emoji' => '🚉', 'type' => 'commute'],
            ['time' => '11:25', 'activity' => 'Train ride home', 'emoji' => '🚃', 'type' => 'commute'],
            ['time' => '11:45', 'activity' => 'Walking home from station', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '12:00', 'activity' => 'Arriving home', 'emoji' => '🏠', 'type' => 'personal'],
            ['time' => '12:05', 'activity' => 'Changing clothes', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '12:15', 'activity' => 'Resting (weekend vibes!)', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '14:00', 'activity' => 'Light homework', 'emoji' => '📖', 'type' => 'studying'],
            ['time' => '16:00', 'activity' => 'Free time', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '18:00', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '18:30', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '19:15', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '19:30', 'activity' => 'Free time relaxing', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '22:30', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '23:00', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '23:30', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ],
        
        'saturday' => [
            ['time' => '08:00', 'activity' => 'Waking up naturally', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '08:15', 'activity' => 'Getting out of bed slowly', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '08:30', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '08:35', 'activity' => 'Taking a long shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '09:00', 'activity' => 'Getting dressed casually', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '09:15', 'activity' => 'Preparing breakfast', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '09:30', 'activity' => 'Eating breakfast leisurely', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '10:00', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '10:15', 'activity' => 'Free time at home', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '12:00', 'activity' => 'Preparing lunch', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '12:30', 'activity' => 'Eating lunch', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '13:00', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '13:15', 'activity' => 'Relaxing', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '15:00', 'activity' => 'Doing some homework', 'emoji' => '📖', 'type' => 'studying'],
            ['time' => '17:00', 'activity' => 'Free time', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '18:30', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '19:00', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '19:45', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '20:00', 'activity' => 'Free time relaxing', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '23:00', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '23:30', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '00:00', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ],
        
        'sunday' => [
            ['time' => '07:00', 'activity' => 'Waking up for church', 'emoji' => '😴', 'type' => 'personal'],
            ['time' => '07:10', 'activity' => 'Getting out of bed', 'emoji' => '🛏️', 'type' => 'personal'],
            ['time' => '07:15', 'activity' => 'Preparing the shower', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '07:20', 'activity' => 'Showering', 'emoji' => '🚿', 'type' => 'personal'],
            ['time' => '07:35', 'activity' => 'Getting dressed nicely', 'emoji' => '👗', 'type' => 'personal'],
            ['time' => '07:45', 'activity' => 'Having quick breakfast', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '07:55', 'activity' => 'Getting ready to leave', 'emoji' => '🎒', 'type' => 'personal'],
            ['time' => '08:00', 'activity' => 'Going to church', 'emoji' => '⛪', 'type' => 'church'],
            ['time' => '10:00', 'activity' => 'Church service ends', 'emoji' => '✅', 'type' => 'church'],
            ['time' => '10:15', 'activity' => 'Walking home from church', 'emoji' => '🚶‍♀️', 'type' => 'commute'],
            ['time' => '10:30', 'activity' => 'Arriving home', 'emoji' => '🏠', 'type' => 'personal'],
            ['time' => '10:35', 'activity' => 'Changing into comfy clothes', 'emoji' => '👕', 'type' => 'personal'],
            ['time' => '10:45', 'activity' => 'Resting', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '12:00', 'activity' => 'Preparing lunch', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '12:30', 'activity' => 'Eating lunch with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '13:15', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '13:30', 'activity' => 'Free time', 'emoji' => '📱', 'type' => 'free'],
            ['time' => '15:00', 'activity' => 'Preparing for the week', 'emoji' => '📋', 'type' => 'studying'],
            ['time' => '16:00', 'activity' => 'Doing some homework', 'emoji' => '📖', 'type' => 'studying'],
            ['time' => '18:00', 'activity' => 'Free time', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '19:00', 'activity' => 'Preparing dinner', 'emoji' => '🍳', 'type' => 'personal'],
            ['time' => '19:30', 'activity' => 'Eating dinner with mom', 'emoji' => '🍽️', 'type' => 'personal'],
            ['time' => '20:15', 'activity' => 'Cleaning dishes', 'emoji' => '🧼', 'type' => 'personal'],
            ['time' => '20:30', 'activity' => 'Free time relaxing', 'emoji' => '😌', 'type' => 'free'],
            ['time' => '22:00', 'activity' => 'Getting ready for bed', 'emoji' => '🌙', 'type' => 'personal'],
            ['time' => '22:30', 'activity' => 'In bed scrolling phone', 'emoji' => '📱', 'type' => 'personal'],
            ['time' => '23:00', 'activity' => 'Sleeping', 'emoji' => '😴', 'type' => 'sleep']
        ]
    ];
}

// Get current activity based on Saitama time
function getMisukiCurrentActivity() {
    date_default_timezone_set('Asia/Tokyo');
    
    $current_day = strtolower(date('l'));
    $current_time = date('H:i');
    
    $schedule = getMisukiWeeklySchedule();
    $today_schedule = $schedule[$current_day];
    
    // Find the current activity
    $current_activity = null;
    for ($i = 0; $i < count($today_schedule); $i++) {
        $activity_time = $today_schedule[$i]['time'];
        
        // Check if current time is past this activity
        if ($current_time >= $activity_time) {
            $current_activity = $today_schedule[$i];
            
            // Check if there's a next activity and we haven't reached it yet
            if ($i + 1 < count($today_schedule)) {
                $next_time = $today_schedule[$i + 1]['time'];
                if ($current_time >= $next_time) {
                    continue; // Move to next activity
                }
            }
        }
    }
    
    // If we're past midnight and before first activity, use last activity from previous day
    if ($current_activity === null) {
        $yesterday = date('l', strtotime('-1 day'));
        $yesterday_schedule = $schedule[strtolower($yesterday)];
        $current_activity = end($yesterday_schedule);
    }
    
    return $current_activity;
}

// Generate status text for display
function getMisukiStatusText() {
    $activity = getMisukiCurrentActivity();
    
    if (!$activity) {
        return "Free time 😌";
    }
    
    return $activity['emoji'] . " " . $activity['activity'];
}

// Check if Misuki is available to chat (not sleeping, not in class)
function isMisukiAvailableToChat() {
    $activity = getMisukiCurrentActivity();
    
    if (!$activity) return true;
    
    $unavailable_types = ['sleep', 'class'];
    
    return !in_array($activity['type'], $unavailable_types);
}

// Get detailed activity info
function getMisukiDetailedStatus() {
    $activity = getMisukiCurrentActivity();
    
    if (!$activity) {
        return [
            'status' => 'Free time',
            'emoji' => '😌',
            'type' => 'free',
            'available' => true
        ];
    }
    
    return [
        'status' => $activity['activity'],
        'emoji' => $activity['emoji'],
        'type' => $activity['type'],
        'available' => !in_array($activity['type'], ['sleep', 'class'])
    ];
}

?>