-- This script updates the 'photo' column for all existing contractors.
-- It assumes that the photo files are stored in the 'public/uploads/photos/' directory
-- and that each photo is named after the contractor's KTP number (e.g., '32010101010010.jpg').

UPDATE contractors 
SET photo = CONCAT('public/uploads/photos/', ktp_no, '.jpg')
WHERE photo IS NULL OR photo = '';
