-- Password for all users is 'password' (hashed with BCRYPT)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Super Admin', 'superadmin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin'),
(2, 'Admin CA Plant', 'admin.ca@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Plant'),
(3, 'Normal User', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User');

INSERT INTO `contractor_companies` (`id`, `name`) VALUES
(1, 'PT. BIKIN ONAR'),
(2, 'PT. PKU'),
(3, 'PT. ATM'),
(4, 'PT. SHK'),
(5, 'PT. TMP');

INSERT INTO `violations` (`id`, `name`, `description`) VALUES
(1, 'KENTUT SEMBARANGAN', 'Mengeluarkan gas dengan sengaja di area kerja yang tidak semestinya.'),
(2, 'Tidak Memakai APD', 'Tidak menggunakan Alat Pelindung Diri sesuai standar K3.'),
(3, 'Merokok di Area Terlarang', 'Merokok di luar area yang telah ditentukan.'),
(4, 'Berkelahi', 'Terlibat dalam perkelahian fisik dengan sesama pekerja.'),
(5, 'Mencuri', 'Mengambil barang yang bukan miliknya.');

-- Total 34 Contractors to match chart percentages
-- CA PLANT: 10 (29.4%)
-- MEI PLANT: 7 (20.6%)
-- PVC PLANT: 6 (17.6%)
-- HPI PLANT: 5 (14.7%)
-- EDC PLANT: 4 (11.8%)
-- VCM PLANT: 2 (5.9%)
INSERT INTO `contractors` (`id`, `id_card`, `ktp_no`, `name`, `company_id`, `plant_location`, `registration_date`, `status`) VALUES
-- CA PLANT (10)
(1, '25001', '32010101010001', 'Andi Budiman', 2, 'CA PLANT', '2023-01-10', 'Active'),
(2, '25002', '32010101010002', 'Budi Santoso', 3, 'CA PLANT', '2023-01-11', 'Active'),
(3, '25003', '32010101010003', 'Candra Wijaya', 4, 'CA PLANT', '2023-01-12', 'Active'),
(4, '25004', '32010101010004', 'Doni Firmansyah', 5, 'CA PLANT', '2023-01-13', 'Active'),
(5, '25005', '32010101010005', 'Eka Prasetya', 2, 'CA PLANT', '2023-01-14', 'Active'),
(6, '25006', '32010101010006', 'Fajar Nugroho', 3, 'CA PLANT', '2023-01-15', 'Active'),
(7, '25007', '32010101010007', 'Guntur Perkasa', 4, 'CA PLANT', '2023-01-16', 'Active'),
(8, '25008', '32010101010008', 'Hadi Mulyono', 5, 'CA PLANT', '2023-01-17', 'Active'),
(9, '25009', '32010101010009', 'Indra Lesmana', 2, 'CA PLANT', '2023-01-18', 'Active'),
(10, '25010', '32010101010010', 'Joko Susilo', 1, 'CA PLANT', '2023-01-19', 'Banned'),
-- MEI PLANT (7)
(11, '25011', '32010101010011', 'Kusuma Bangsa', 3, 'MEI PLANT', '2023-02-01', 'Active'),
(12, '25012', '32010101010012', 'Lutfi Hakim', 4, 'MEI PLANT', '2023-02-02', 'Active'),
(13, '25013', '32010101010013', 'Mahmud Efendi', 5, 'MEI PLANT', '2023-02-03', 'Active'),
(14, '25014', '32010101010014', 'Nurhadi', 2, 'MEI PLANT', '2023-02-04', 'Active'),
(15, '25015', '32010101010015', 'Oscar Daniel', 3, 'MEI PLANT', '2023-02-05', 'Active'),
(16, '25016', '32010101010016', 'Putu Gede', 4, 'MEI PLANT', '2023-02-06', 'Active'),
(17, '25017', '32010101010017', 'Qomarudin', 5, 'MEI PLANT', '2023-02-07', 'Active'),
-- PVC PLANT (6)
(18, '25018', '32010101010018', 'Rahmat Hidayat', 2, 'PVC PLANT', '2023-03-01', 'Active'),
(19, '25019', '32010101010019', 'Samsul Arifin', 3, 'PVC PLANT', '2023-03-02', 'Active'),
(20, '25020', '32010101010020', 'Toni Stark', 4, 'PVC PLANT', '2023-03-03', 'Active'),
(21, '25021', '32010101010021', 'Umar Bakri', 5, 'PVC PLANT', '2023-03-04', 'Active'),
(22, '25022', '32010101010022', 'Victor Imanuel', 2, 'PVC PLANT', '2023-03-05', 'Active'),
(23, '25023', '32010101010023', 'Wahyu Abadi', 3, 'PVC PLANT', '2023-03-06', 'Active'),
-- HPI PLANT (5)
(24, '25024', '32010101010024', 'Xavier Hernandez', 4, 'HPI PLANT', '2023-04-01', 'Active'),
(25, '25025', '32010101010025', 'Yusuf Mansur', 5, 'HPI PLANT', '2023-04-02', 'Active'),
(26, '25026', '32010101010026', 'Zainal Abidin', 2, 'HPI PLANT', '2023-04-03', 'Active'),
(27, '25027', '32010101010027', 'Ahmad Dhani', 3, 'HPI PLANT', '2023-04-04', 'Active'),
(28, '25028', '32010101010028', 'Bruce Wayne', 4, 'HPI PLANT', '2023-04-05', 'Active'),
-- EDC PLANT (4)
(29, '25029', '32010101010029', 'Clark Kent', 5, 'EDC PLANT', '2023-05-01', 'Active'),
(30, '25030', '32010101010030', 'Diana Prince', 2, 'EDC PLANT', '2023-05-02', 'Active'),
(31, '25031', '32010101010031', 'Peter Parker', 3, 'EDC PLANT', '2023-05-03', 'Active'),
(32, '25032', '32010101010032', 'Tony Soprano', 4, 'EDC PLANT', '2023-05-04', 'Active'),
-- VCM PLANT (2)
(33, '25033', '32010101010033', 'Walter White', 5, 'VCM PLANT', '2023-06-01', 'Active'),
(34, '25034', '32010101010034', 'Jesse Pinkman', 1, 'VCM PLANT', '2023-06-02', 'Banned');

INSERT INTO `sanctions` (`contractor_id`, `violation_id`, `sanction_type`, `start_date`, `is_permanent`, `reason`) VALUES
(10, 1, 'BANNED', '2023-10-01', 1, 'KENTUT SEMBARANGAN di ruang kontrol.'),
(34, 3, 'BANNED', '2023-10-05', 0, 'Merokok di area produksi VCM.');

INSERT INTO `attendances` (`contractor_id`, `plant_location`, `check_in_time`, `check_out_time`, `work_hours`) VALUES
(1, 'CA PLANT', '2023-10-07 08:00:00', '2023-10-07 17:00:00', 8.00),
(2, 'CA PLANT', '2023-10-07 08:01:00', '2023-10-07 17:02:00', 8.02),
(11, 'MEI PLANT', '2023-10-07 07:59:00', '2023-10-07 17:01:00', 8.03);

INSERT INTO `system_settings` (`key`, `value`) VALUES
('app_logo', 'assets/logo.png'),
('running_text', 'Selamat datang di area plant. Utamakan keselamatan kerja dan selalu gunakan APD.'),
('safety_video_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&loop=1&playlist=dQw4w9WgXcQ');
