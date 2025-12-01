-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 01, 2025 at 06:29 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `airlines`
--

-- --------------------------------------------------------

--
-- Table structure for table `airports`
--

CREATE TABLE `airports` (
  `IATACode` char(3) NOT NULL,
  `AirportName` varchar(100) NOT NULL,
  `City` varchar(50) NOT NULL,
  `CountryRegion` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `airports`
--

INSERT INTO `airports` (`IATACode`, `AirportName`, `City`, `CountryRegion`) VALUES
('AAP', 'AP Tumenggung Pranoto', 'Samarinda', 'Indonesia'),
('AAS', 'Apalapsili', 'Apalapsili', 'Indonesia'),
('AAV', 'Allah Valley Airport', 'Surallah', 'Philippines'),
('ABU', 'Haliwen', 'Atambua-Timor Island', 'Indonesia'),
('AEG', 'Aek Godang', 'Padang Sidempuan-Sumatra Island', 'Indonesia'),
('AGD', 'Anggi Airport', 'Anggi', 'Indonesia'),
('AGJ', 'Aguni Airport', 'Aguni', 'Japan'),
('AHA', 'Naha Air Force Base Airport', 'Okinawa', 'Japan'),
('AHI', 'Amahai', 'Amahai-Seram Island', 'Indonesia'),
('AKJ', 'Asahikawa Airport', 'Asahikawa', 'Japan'),
('AKQ', 'Gunung Batin', 'Astraksetra-Sumatra Island', 'Indonesia'),
('AKY', 'Sittwe Airport', 'Sittwe', 'Myanmar'),
('AMQ', 'Pattimura', 'Kota Ambon', 'Indonesia'),
('AOJ', 'Aomori Airport', 'Aomori', 'Japan'),
('AOR', 'Alorsetar Airport', 'Alorsetar', 'Malaysia'),
('ARD', 'Mali', 'Alor', 'Indonesia'),
('ARJ', 'Arso', 'Arso-Papua Island', 'Indonesia'),
('ASJ', 'Amami Airport', 'Amami', 'Japan'),
('AUT', 'Atauro Airport', 'Atauro', 'Timor-Leste'),
('AXT', 'Akita Airport', 'Akita', 'Japan'),
('AYW', 'Ayawasi Airport', 'Ayawasi', 'Indonesia'),
('BAG', 'Loakan Airport', 'Baguio', 'Philippines'),
('BAO', 'Udorn Air Base', 'Ban Mak Khaen', 'Thailand'),
('BBM', 'Battambang Airport', 'Battambang', 'Cambodia'),
('BBN', 'Bario Airport', 'Bario', 'Malaysia'),
('BCD', 'Bacolod City Domestic Airport', 'Bacolod', 'Philippines'),
('BCH', 'Cakung Airport', 'Baucau', 'Timor-Leste'),
('BDJ', 'Syamsudin Noor', 'Banjarmasin', 'Indonesia'),
('BDO', 'Husein Sastranegara', 'Bandung', 'Indonesia'),
('BEJ', 'Kalimarau Airport', 'Tanjung Redeb', 'Indonesia'),
('BFV', 'Buri Ram Airport', 'Buri Ram', 'Thailand'),
('BKI', 'Kota Kinabalu International Airport', 'Kota Kinabalu', 'Malaysia'),
('BKK', 'Suvarnabhumi Airport', 'Bangkok', 'Thailand'),
('BKM', 'Bakelalan Airport', 'Bakelalan', 'Malaysia'),
('BKS', 'Fatmawati Soekarno', 'Bengkulu', 'Indonesia'),
('BKZ', 'Bukoba Airport', 'Bukoba', 'Malaysia'),
('BMO', 'Bhamo Airport', 'Bhamo', 'Myanmar'),
('BMV', 'Phung-Doc Airport', 'Banmethuot', 'Vietnam'),
('BNQ', 'Baganga Airport', 'Baganga, Davao Oriental', 'Philippines'),
('BPE', 'Qinhuangdao Beidaihe Airport', 'Qinhuangdao', 'Myanmar'),
('BPH', 'Bislig Airport', 'Bislig', 'Philippines'),
('BQA', 'Baler Airport', 'Baler', 'Philippines'),
('BSE', 'Sematan Airport', 'Sematan', 'Malaysia'),
('BSO', 'Basco Airport', 'Basco', 'Philippines'),
('BSX', 'Pathein Airport', 'Pathein', 'Myanmar'),
('BTH', 'Hang Nadim', 'Batam', 'Indonesia'),
('BTJ', 'Sultan Iskandar Muda', 'Banda Aceh', 'Indonesia'),
('BTU', 'Bintulu Airport', 'Bintulu', 'Malaysia'),
('BTW', 'Batu Licin Airport', 'Batu Licin', 'Indonesia'),
('BUI', 'Bokondini Airport', 'Bokondini', 'Indonesia'),
('BUW', 'Betoambari', 'Baubu', 'Indonesia'),
('BWH', 'Butterworth Airport', 'Butterworth', 'Malaysia'),
('BWN', 'Brunei International Airport', 'Bandar Seri Begawan', 'Brunei'),
('BXD', 'Bade Airport', 'Bade', 'Indonesia'),
('BXM', 'Batom Airport', 'Batom', 'Indonesia'),
('BXT', 'Bontang Airport', 'Bontang', 'Indonesia'),
('BXU', 'Butuan Airport (Bancasi Airport)', 'Butuan', 'Philippines'),
('CAH', 'Cà Mau Airport', 'Ca Mau City', 'Vietnam'),
('CBN', 'Penggung', 'Cirebon-Java Island', 'Indonesia'),
('CBO', 'Awang Airport', 'Cotabato', 'Philippines'),
('CDY', 'Cagayan De Sulu Airport', 'Mapun, Tawi-Tawi', 'Philippines'),
('CEB', 'Mactan-Cebu International Airport', 'Cebu / Lapu-Lapu', 'Philippines'),
('CEI', 'Chiang Rai Airport', 'Chiang Rai', 'Thailand'),
('CGK', 'Soekarno-Hatta Intl', 'Jakarta', 'Indonesia'),
('CGM', 'Mambajao Airport (Camiguin Airport)', 'Mambajao', 'Philippines'),
('CGY', 'Lumbia Airport', 'Cagayan De Oro', 'Philippines'),
('CJM', 'Chumphon Airport', 'Chumphon', 'Thailand'),
('CMJ', 'Qimei Airport', 'Qimei', 'Taiwan'),
('CNX', 'Chiang Mai International Airport', 'Chiang Mai', 'Thailand'),
('CPF', 'Nglonram', 'Cepu', 'Indonesia'),
('CRK', 'Diosdado Macapagal International Airport (Clark International Airport)', 'Manila', 'Philippines'),
('CTS', 'New Chitose Airport', 'Sapporo', 'Japan'),
('CXP', 'Tunggul Wulung', 'Cilacap', 'Indonesia'),
('CXR', 'Cam Ranh Airport', 'Nha Trang', 'Vietnam'),
('CYI', 'Chiayi Airport', 'Chiayi', 'Taiwan'),
('CYZ', 'Cauayan Airport', 'Cauayan', 'Philippines'),
('DAD', 'Da Nang Airport', 'Da Nang', 'Vietnam'),
('DGT', 'Sibulan Airport', 'Dumaguete City', 'Philippines'),
('DIL', 'Presidente Nicolau Lobato International Airport', 'Dili', 'Timor-Leste'),
('DIN', 'Gialam Airport', 'Dien Bien Phu', 'Vietnam'),
('DJJ', 'Sentani', 'Jayapura', 'Indonesia'),
('DLI', 'Lienkhang Airport', 'Dalat', 'Vietnam'),
('DMK', 'Don Mueang International Airport', 'Bangkok', 'Thailand'),
('DNA', 'Kadena AB Airport', 'Okinawa', 'Japan'),
('DOB', 'Rar Gwamar', 'DOBO', 'Indonesia'),
('DPL', 'Dipolog Airport', 'Dipolog', 'Philippines'),
('DPS', 'Ngurah Rai', 'Denpasar-Bali', 'Indonesia'),
('DRH', 'Dabra Airport', 'Dabra', 'Indonesia'),
('DTD', 'Datadawai Airport', 'Datawai', 'Indonesia'),
('DTE', 'Bagasbas Airport', 'Daet', 'Philippines'),
('DUM', 'Pinang Kampai', 'Dumai', 'Indonesia'),
('DVO', 'Francisco Bangoy International Airport', 'Davao', 'Philippines'),
('ELR', 'Elelim Airport', 'Elelim', 'Indonesia'),
('ENE', 'H.Hasan Aroeboesman', 'Ende', 'Indonesia'),
('ENG', 'Enggano Airport', 'Bengkulu', 'Indonesia'),
('EWE', 'Ewer Airport', 'Ewer', 'Indonesia'),
('EWI', 'Enarotali Airport', 'Enarotali', 'Indonesia'),
('FKJ', 'Fukui Airport', 'Fukui', 'Japan'),
('FKQ', 'Torea', 'Fak Fak', 'Indonesia'),
('FKS', 'Fukushima Airport', 'Fukushima', 'Japan'),
('FOO', 'Numfoor Airport', 'Numfoor', 'Indonesia'),
('FSZ', 'Mt Fuji Shizuoka Airport', 'Shizuoka Honshu', 'Japan'),
('FUK', 'Fukuoka Airport', 'Fukuoka', 'Japan'),
('GAJ', 'Junmachi Airport', 'Yamagata', 'Japan'),
('GAV', 'Gag Island Airport', 'Gag Island', 'Indonesia'),
('GAW', 'Gangaw Airport', 'Gangaw', 'Myanmar'),
('GEB', 'Gebe Airport', 'GEB', 'Indonesia'),
('GES', 'General Santos International Airport (Buayan Airport)', 'General Santos', 'Philippines'),
('GLX', 'Gamarmalamo', 'Galela', 'Indonesia'),
('GNI', 'Lüdao Airport', 'Green Island', 'Taiwan'),
('GNS', 'Binaka', 'Gunung Sitoli', 'Indonesia'),
('GSA', 'Long Pasia Airport', 'Long Miau', 'Malaysia'),
('GTO', 'Djalaluddin', 'Gorontalo', 'Indonesia'),
('GWA', 'Gwa Airport', 'Gwa', 'Myanmar'),
('HAC', 'Hachijo Jima Airport', 'Hachijo Jima', 'Japan'),
('HAN', 'Noi Bai International Airport', 'Hanoi', 'Vietnam'),
('HCN', 'Hengchun Airport', 'Hengchun', 'Taiwan'),
('HDY', 'Hat Yai Airport', 'Hat Yai', 'Thailand'),
('HEB', 'Hinthada Airport', 'Hinthada', 'Myanmar'),
('HEH', 'Heho Airport', 'Heho', 'Myanmar'),
('HGN', 'Mae Hong Son Airport', 'Mae Hong Son', 'Thailand'),
('HHE', 'Hachinohe Airport', 'Hachinohe', 'Japan'),
('HHQ', 'Hualtin Airport', 'Hualtin', 'Thailand'),
('HIJ', 'Hiroshima International Airport', 'Hiroshima', 'Japan'),
('HKD', 'Hakodate Airport', 'Hakodate', 'Japan'),
('HKG', 'Hong Kong International Airport', 'Hong Kong', 'Hong Kong'),
('HKT', 'Phuket International Airport', 'Phuket', 'Thailand'),
('HLP', 'Halim Perdanakusuma', 'Jakarta', 'Indonesia'),
('HNA', 'Hanamaki Airport', 'Hanamaki', 'Japan'),
('HND', 'Tokyo Haneda International Airport', 'Tokyo', 'Japan'),
('HOE', 'Houeisay Airport', 'Houeisay', 'Laos'),
('HOO', 'Nhon Co Airfield', 'Quang Duc', 'Vietnam'),
('HOX', 'Hommalinn Airport', 'Hommalinn', 'Myanmar'),
('HPH', 'Catbi Airport', 'Haiphong', 'Vietnam'),
('HSG', 'Saga Airport', 'Saga', 'Japan'),
('HTR', 'Hateruma Airport', 'Hateruma', 'Japan'),
('HUI', 'Hue Airport', 'Hue', 'Vietnam'),
('HUN', 'Hualien Airport', 'Hualien', 'Taiwan'),
('IGN', 'Maria Cristina Airport', 'Iligan', 'Philippines'),
('IKI', 'Iki Airport', 'Iki', 'Japan'),
('ILA', 'Illaga Airport', 'Illaga', 'Indonesia'),
('ILO', 'Iloilo International Airport', 'Iloilo', 'Philippines'),
('INX', 'Inanwatan Airport', 'Inanwatan', 'Indonesia'),
('IPH', 'Ipoh Airport', 'Ipoh', 'Malaysia'),
('ISG', 'Ishigaki Airport', 'Ishigaki', 'Japan'),
('ITM', 'Itami Airport', 'Osaka', 'Japan'),
('IUL', 'Ilu Airport', 'Ilu', 'Indonesia'),
('IWJ', 'Iwami Airport', 'Iwami', 'Japan'),
('IWO', 'Iwo Jima Airbase Airport', 'Iwo Jima Vol', 'Japan'),
('IZO', 'Izumo Airport', 'Izumo', 'Japan'),
('JHB', 'Johor Airport', 'Johor', 'Malaysia'),
('JKT', 'Metropolitan Area', 'Jakarta', 'Indonesia'),
('JOG', 'Adisutjipto', 'Yogyakarta', 'Indonesia'),
('JOL', 'Jolo Airport', 'Jolo', 'Philippines'),
('KAW', 'Kawthaung Airport', 'Kawthaung', 'Myanmar'),
('KAZ', 'Kaubang', 'Kau', 'Indonesia'),
('KBF', 'Karubaga Airport', 'Karubaga', 'Indonesia'),
('KBR', 'Kota Bharu Airport', 'Kota Bharu', 'Malaysia'),
('KBU', 'Gusti Sjamsir Alam', 'Kotabaru', 'Indonesia'),
('KBV', 'Krabi Airport', 'Krabi', 'Thailand'),
('KBX', 'Kambuaya', 'Ayamaru', 'Indonesia'),
('KCD', 'Kamur Airport', 'Kamur', 'Indonesia'),
('KCH', 'Kuching Airport', 'Kuching', 'Malaysia'),
('KCI', 'Kon Airport', 'Kon', 'Timor-Leste'),
('KCZ', 'Kochi Airport', 'Kochi', 'Japan'),
('KDI', 'Halu Oleo', 'Kendari', 'Indonesia'),
('KDT', 'Kamphaeng Saen Airport', 'Nakhon Pathom', 'Thailand'),
('KEA', 'Kerki Intl.', 'Kerki', 'Indonesia'),
('KEI', 'Kepi Airport', 'Kepi', 'Indonesia'),
('KEQ', 'Kebar Airport', 'Kebar', 'Indonesia'),
('KET', 'Kengtung Airport', 'Kengtung', 'Myanmar'),
('KGU', 'Keningau Airport', 'Keningau', 'Malaysia'),
('KHH', 'Kaohsiung International Airport', 'Kaohsiung', 'Taiwan'),
('KHM', 'Kanti Airport', 'Kanti', 'Myanmar'),
('KIJ', 'Niigata Airport', 'Niigata', 'Japan'),
('KIX', 'Kansai International Airport', 'Osaka', 'Japan'),
('KKC', 'Khon Kaen Airport', 'Khon Kaen', 'Thailand'),
('KKM', 'Sa Pran Nak Airport', 'Sa Pran Nak', 'Thailand'),
('KKX', 'Kikaiga Shima Airport', 'Kikaiga Shima', 'Japan'),
('KKZ', 'Kaoh Kong Airport', 'Kaoh Kong', 'Cambodia'),
('KLO', 'Kalibo Airport', 'Kalibo', 'Philippines'),
('KLQ', 'Keluang Airport', 'Keluang', 'Indonesia'),
('KMI', 'Miyazaki Airport', 'Miyazaki', 'Japan'),
('KMJ', 'Kumamoto Airport', 'Kumamoto', 'Japan'),
('KMM', 'Kimam Airport', 'Kimam', 'Indonesia'),
('KMQ', 'Komatsu Airport', 'Komatsu', 'Japan'),
('KMV', 'Kalay Airport', 'Kalemyo', 'Myanmar'),
('KNG', 'Kaimana', 'Utarom', 'Indonesia'),
('KNH', 'Kinmen Shangyi Airport', 'Kinmen', 'Taiwan'),
('KNO', 'Kuala Namu', 'Medan', 'Indonesia'),
('KOD', 'Kotabangun Airport', 'Kotabangun', 'Indonesia'),
('KOE', 'El Tari', 'Kupang', 'Indonesia'),
('KOJ', 'Kagoshima Airport', 'Kagoshima', 'Japan'),
('KON', 'Kontum Airport', 'Kontum', 'Vietnam'),
('KOP', 'Nakhon Phanom Airport', 'Nakhon Phanom', 'Thailand'),
('KOS', 'Sihanoukville International Airport', 'Sihanukville', 'Cambodia'),
('KOX', 'Kokonao Airport', 'Kokonao', 'Indonesia'),
('KPI', 'Kapit Airport', 'Kapit', 'Malaysia'),
('KRC', 'Depati Parbo', 'Kerinci', 'Indonesia'),
('KTD', 'Kitadaito Airport', 'Kitadaito', 'Japan'),
('KTE', 'Kerteh Airport', 'Kerteh', 'Malaysia'),
('KTG', 'Rahadi Osman', 'Ketapang', 'Indonesia'),
('KTI', 'Kratie Airport', 'Kratie', 'Cambodia'),
('KTZ', 'Kwun Tong Airport', '    Kwun Tong', 'Hong Kong'),
('KUA', 'Kuantan Airport', 'Kuantan', 'Malaysia'),
('KUD', 'Kudat Airport', 'Kudat', 'Malaysia'),
('KUH', 'Kushiro Airport', 'Kushiro', 'Japan'),
('KUJ', 'Kushimoto Airport', 'Kushimoto', 'Japan'),
('KUL', 'Kuala Lumpur International Airport', 'Kuala Lumpur', 'Malaysia'),
('KUM', 'Yakushima Airport', 'Yakushima', 'Japan'),
('KWB', 'Dewadaru', 'Karimunjawa', 'Indonesia'),
('KYD', 'Lanyu Airport', 'Orchid Island', 'Taiwan'),
('KYP', 'Kyaukpyu Airport', 'Kyaukpyu', 'Myanmar'),
('KYT', 'Kyauktu Airport', 'Kyauktu', 'Myanmar'),
('KZC', 'Kampong Chhnang Airport', 'Kampong Chhnang', 'Cambodia'),
('KZD', 'KrakorAirport', 'Krakor', 'Cambodia'),
('KZK', 'Kompong Thom Airport', 'Kompong Thom', 'Cambodia'),
('LAH', 'Oesman Sadik', 'Labuha', 'Indonesia'),
('LAO', 'Laoag International Airport', 'Laoag', 'Philippines'),
('LBJ', 'Komodo', 'Labuan Bajo', 'Indonesia'),
('LBP', 'Long Banga Airport', 'Long Banga', 'Malaysia'),
('LBU', 'Labuan Airport', 'Labuan', 'Malaysia'),
('LBW', 'Yuvai Semaring', 'Long Bawan', 'Indonesia'),
('LBX', 'Lubang Airport', 'Lubang', 'Philippines'),
('LDU', 'Lahadbatu Airport', 'Lahadbatu', 'Malaysia'),
('LGK', 'Langkawi Airport', 'Langkawi', 'Malaysia'),
('LGL', 'Long Lellang Airport', 'Long Lellang', 'Malaysia'),
('LGP', 'Legazpi Airport', 'Legazpi', 'Philippines'),
('LHI', 'Lereh Airport', 'Lereh', 'Indonesia'),
('LII', 'Mulia Airport', 'Mulia', 'Indonesia'),
('LIW', 'Loikaw Airport', 'Loikaw', 'Myanmar'),
('LKA', 'Gewayantana', 'Larantuka', 'Indonesia'),
('LKH', 'Long Akah Airport', 'Long Akah', 'Malaysia'),
('LLM', 'Long Lama Airport', 'Long Lama', 'Malaysia'),
('LLN', 'Kelila Airport', 'Kelila', 'Indonesia'),
('LMN', 'Limbang Airport', 'Limbang', 'Malaysia'),
('LOE', 'Loei Airport', 'Loei', 'Thailand'),
('LOP', 'Lombok International', 'Priya', 'Indonesia'),
('LPQ', 'Luang Prabang International Airport', 'Luang Prabang', 'Laos'),
('LPT', 'Lampang Airport', 'Lampang', 'Thailand'),
('LPU', 'Long Apung Airport', 'Long Apung', 'Indonesia'),
('LSH', 'Lashio Airport', 'Lashio', 'Myanmar'),
('LSM', 'Long Semado Airport', 'Long Semado', 'Malaysia'),
('LSU', 'Long Sukang Airport', 'Long Sukang', 'Malaysia'),
('LSW', 'Malikussaleh', 'Lhok Seumawe', 'Indonesia'),
('LSX', 'Lhok Sukon Airport', 'Lhok Sukon', 'Indonesia'),
('LUV', 'Dumatubun', 'Langgur', 'Indonesia'),
('LUW', 'S.Aminuddin Amir', 'Luwuk', 'Indonesia'),
('LWE', 'Wunopito', 'Lewoleba', 'Indonesia'),
('LWY', 'Lawas Airport', 'Lawas', 'Malaysia'),
('LXG', 'Luang Namtha Airport', 'Luang Namtha', 'Laos'),
('LYK', 'Lunyuk', 'Sumbawa', 'Indonesia'),
('LZN', 'Matsu Nangan Airport', 'Lienchiang (Nangan)', 'Taiwan'),
('MAL', 'Falabisahaya', 'Mangole', 'Indonesia'),
('MAQ', 'Mae Sot Airport', 'Mae Sot', 'Thailand'),
('MBE', 'Monbetsu Airport', 'Monbetsu', 'Japan'),
('MBO', 'Mamburao Airport', 'Mamburao', 'Philippines'),
('MBT', 'Masbate Airport', 'Masbate', 'Philippines'),
('MDC', 'Sam Ratulangi', 'Manado', 'Indonesia'),
('MDL', 'Mandalay Airport', 'Mandalay', 'Myanmar'),
('MDP', 'Mindiptana Airport', 'Mindiptana', 'Indonesia'),
('MEP', 'Mersing Airport', 'Mersing', 'Malaysia'),
('MEQ', 'Nagan Raya', 'Cut Nyak Dien', 'Indonesia'),
('MES', 'Polonia', 'Medan', 'Indonesia'),
('MFK', 'Matsu Beigan Airport', 'Lienchiang (Beigan)', 'Taiwan'),
('MGK', 'Mong Tong Airport', 'Mong Tong', 'Myanmar'),
('MGU', 'Manaung Airport', 'Manaung', 'Myanmar'),
('MGZ', 'Myeik Airport', 'Myeik', 'Myanmar'),
('MJU', 'Tampa Padang', 'Mamuju', 'Indonesia'),
('MJY', 'Motygino Airport', 'Motygino', 'Indonesia'),
('MKM', 'Mukah Airport', 'Mukah', 'Malaysia'),
('MKQ', 'Mopah', 'Marauke', 'Indonesia'),
('MKW', 'Rendani', 'Manokwari', 'Indonesia'),
('MKZ', 'Malacca Airport', 'Malacca', 'Malaysia'),
('MLG', 'Abdul Rachman Saleh', 'Malang', 'Indonesia'),
('MLP', 'Malabang Airport', 'Malabang', 'Philippines'),
('MMD', 'Maridor Airport', 'Minami Daito', 'Japan'),
('MMJ', 'Matsumoto Airport', 'Matsumoto', 'Japan'),
('MNA', 'Melangguane Airport', 'Melangguane', 'Indonesia'),
('MNL', 'Ninoy Aquino International Airport', 'Manila', 'Philippines'),
('MNU', 'Maulmyine Airport', 'Maulmyine', 'Myanmar'),
('MOE', 'Momeik Airport', 'Momeik', 'Myanmar'),
('MOF', 'Fransiskus X. Seda', 'Maumere', 'Indonesia'),
('MOG', 'Mong Hsat Airport', 'Mong Hsat', 'Myanmar'),
('MPC', 'Muko-Muko Airport', 'Muko-Muko', 'Indonesia'),
('MPH', 'Malay Airport (Godofredo P. Ramos Airport)', 'Caticlan/Boracay', 'Philippines'),
('MPT', 'Maliana Airport', 'Maliana', 'Timor-Leste'),
('MRQ', 'Marinduque Airport', 'Gasan', 'Philippines'),
('MSI', 'Masalembo Airport', 'Malasembo', 'Indonesia'),
('MSJ', 'Misawa Airport', 'Misawa', 'Japan'),
('MTW', 'Manitowoc County', 'Manitowoc', 'Indonesia'),
('MUF', 'Muting Airport', 'Muting', 'Indonesia'),
('MUR', 'Marudi Airport', 'Marudi', 'Malaysia'),
('MWK', 'Tarempa', 'Matak', 'Indonesia'),
('MWQ', 'Magway Airport', 'Magway', 'Myanmar'),
('MXB', 'Andi Jemma', 'Masamba', 'Indonesia'),
('MXI', 'Imelda R. Marcos Airport', 'Mati', 'Philippines'),
('MYJ', 'Matsuyama Airport', 'Matsuyama', 'Japan'),
('MYT', 'Myitkyina Airport', 'Myitkyina', 'Myanmar'),
('MYY', 'Miri Airport', 'Miri', 'Malaysia'),
('MZG', 'Penghu Airport', 'Penghu', 'Taiwan'),
('MZS', 'Moradabad Airport', 'Moradabad', 'Malaysia'),
('MZV', 'Mulu Airport', 'Mulu', 'Malaysia'),
('NAF', 'Banaina Airport', 'Banaina', 'Indonesia'),
('NAH', 'Naha', 'Tahuna', 'Indonesia'),
('NAK', 'Nakhon Ratchosima Airport', 'Nakhon Ratchosima', 'Thailand'),
('NAM', 'Namlea Airport', 'Namlea', 'Indonesia'),
('NAW', 'Narathiwat Airport', 'Narathiwat', 'Thailand'),
('NBX', 'Nabire Airport', 'Nabire', 'Indonesia'),
('NDA', 'Bandaneira', 'Banderia Island', 'Indonesia'),
('NEU', 'Sam Neua Airport', 'Sam Neua', 'Laos'),
('NGO', 'Chu Bu Centrair International Central Japan International Airport', 'Tokoname', 'Japan'),
('NHA', 'Nha Trang Airport', 'Nha Trang', 'Vietnam'),
('NJA', 'Atsugi NAS Airport', 'Atsugi', 'Japan'),
('NKD', 'Sinak Airport', 'Sinak', 'Indonesia'),
('NMS', 'Namsang Airport', 'Namsang', 'Myanmar'),
('NMT', 'Namtu Airport', 'Namtu', 'Myanmar'),
('NNK', 'Naknek Airport', 'Naknek', 'Indonesia'),
('NNT', 'Nan Airport', 'Nan', 'Thailand'),
('NPO', 'Nangapinoh Airport', 'Nangipinoh', 'Indonesia'),
('NRE', 'Namrole Airport', 'Namrole', 'Indonesia'),
('NRT', 'Narita International Airport', 'Tokyo', 'Japan'),
('NST', 'Nakhon Si Thammarat Airport', 'Nakhon Si Thammarat', 'Thailand'),
('NTI', 'Stenkol', 'Bintuni', 'Indonesia'),
('NTQ', 'Noto Airport', 'Wajima', 'Japan'),
('NTX', 'Ranai', 'Natuna', 'Indonesia'),
('NYT', 'Nay Pyi Taw International Airport', 'Naypyidaw', 'Myanmar'),
('NYU', 'Nyaung Airport', 'Nyaung', 'Myanmar'),
('OBD', 'Obano Airport', 'Obano', 'Indonesia'),
('OBO', 'Obihiro Airport', 'Obihiro', 'Japan'),
('ODN', 'Long Seridan Airport', 'Long Seridan', 'Malaysia'),
('ODY', 'Oudomxai Airport', 'Muang Xay', 'Laos'),
('OEC', 'Oecussi Airport', 'Oecussi-Ambeno', 'Timor-Leste'),
('OIM', 'Oshima Airport', 'Oshima', 'Japan'),
('OIR', 'Okushiri Airport', 'Okushiri', 'Japan'),
('OIT', 'Oita Airport', 'Oita', 'Japan'),
('OKD', 'Okadama Airport', 'Sapporo', 'Japan'),
('OKE', 'Okierabu Airport', 'Okierabu', 'Japan'),
('OKJ', 'Okayama Airport', 'Okayama', 'Japan'),
('OKL', 'Gunung Bintang', 'Oksibil', 'Indonesia'),
('OKO', 'Yokota Afb Airport', 'Tokyo', 'Japan'),
('OKQ', 'Okaba Airport', 'Okaba', 'Indonesia'),
('OMC', 'Ormoc Airport', 'Ormoc', 'Philippines'),
('OMJ', 'Omura Airport', 'Omura', 'Japan'),
('OMY', 'Preah Vinhear Airport', 'Tbeng Meanchey', 'Cambodia'),
('ONI', 'Moanamani Airport', 'Moanamani', 'Indonesia'),
('ONJ', 'Odate Noshiro Airport', 'Odate Noshiro', 'Japan'),
('OTI', 'Pitu', 'Morotai Island', 'Indonesia'),
('OZC', 'Labo Airport', 'Ozamis', 'Philippines'),
('PAG', 'Pagadian Airport', 'Pagadian', 'Philippines'),
('PAN', 'Pattani Airport', 'Pattani', 'Thailand'),
('PAU', 'Pauk Airport', 'Pauk', 'Myanmar'),
('PAY', 'Pamol Airport', 'Pamol', 'Malaysia'),
('PBU', 'Putao Airport', 'Putao', 'Myanmar'),
('PCB', 'Pondok Cabe Airport', 'Pondok Cabe', 'Indonesia'),
('PCQ', 'Boun Neua Airport', 'Phongsaly', 'Laos'),
('PDG', 'Minangkabau', 'Padang', 'Indonesia'),
('PDO', 'Pendopo Airport', 'Pendopo', 'Indonesia'),
('PEN', 'Penang International Airport', 'Penang', 'Malaysia'),
('PGK', 'Depati Amir', 'Pangkalpinang', 'Indonesia'),
('PHA', 'Phan Rang Air Base', 'Phan Rang', 'Vietnam'),
('PHH', 'Pokhara International Airport', 'Pokhara', 'Vietnam'),
('PHS', 'Phitsanulok Airport', 'Phitsanulok', 'Thailand'),
('PHU', 'Phu Vinh Airport', 'Phu Vinh', 'Vietnam'),
('PHY', 'Phetchabun Airport', 'Phetchabun', 'Thailand'),
('PKG', 'Pangkor Airport', 'Pangkor Island', 'Malaysia'),
('PKK', 'Pakokku Airport', 'Pakokku', 'Myanmar'),
('PKN', 'Iskandar', 'Pangkalanbuun', 'Indonesia'),
('PKU', 'Sultan Syarif Kasim II', 'Pekanbaru', 'Indonesia'),
('PKY', 'Tjilik Riwut', 'Palangkaraya', 'Indonesia'),
('PKZ', 'Pakse International Airport', 'Pakse', 'Laos'),
('PLM', 'S M Badaruddin II', 'Palembang', 'Indonesia'),
('PLW', 'Mutiara', 'Palu', 'Indonesia'),
('PNH', 'Phnom Penh International Airport', 'Phnom Penh', 'Cambodia'),
('PNK', 'Supadio', 'Pontianak', 'Indonesia'),
('PPJ', 'Panjang Island Airport', 'Pajang Islang', 'Indonesia'),
('PPR', 'Pasir Pangarayan Airport', 'Pasir Pangarayan', 'Indonesia'),
('PPS', 'Puerto Princesa International Airport', 'Puerto Princesa', 'Philippines'),
('PPU', 'Papun Airport', 'Pa Pun', 'Myanmar'),
('PQC', 'Phu Quoc Island International Airport', 'Phu Quoc Island', 'Vietnam'),
('PRH', 'Phrae Airport', 'Phrae', 'Thailand'),
('PRU', 'Pyay Airport', 'Pye', 'Myanmar'),
('PSJ', 'Kasinguncu', 'Poso', 'Indonesia'),
('PSU', 'Pangsuma', 'Putussibau', 'Indonesia'),
('PWL', 'Wirasaba', 'Purwokerto', 'Indonesia'),
('PXU', 'Pleiku Airport', 'Pleiku', 'Vietnam'),
('QPG', 'Paya Lebar Airport', 'Singapore', 'Singapore'),
('RAQ', 'Sugimanuru', 'Muna', 'Indonesia'),
('RBE', 'Ratanakiri Airport', 'Ratanakiri', 'Cambodia'),
('RDE', 'Jahabra', 'Merdey', 'Indonesia'),
('RDN', 'Redang Airport', 'Redang', 'Malaysia'),
('RGN', 'Yangon International Airport', 'Yangon', 'Myanmar'),
('RGT', 'Japura', 'Rengat', 'Indonesia'),
('RIS', 'Rishiri Airport', 'Rishiri', 'Japan'),
('RKI', 'Sipora', 'Rokot', 'Indonesia'),
('RMQ', 'Cingcyuangang Airport', 'Taichung', 'Taiwan'),
('RNU', 'Ranau Airport', 'Ranau', 'Malaysia'),
('ROI', 'Roi Et Airport', 'Roi Et', 'Thailand'),
('RSK', 'Abresso', 'Ransiki', 'Indonesia'),
('RTG', 'Frans Sales Lega', 'Ruteng', 'Indonesia'),
('RTI', 'David C. Saudale', 'Roti', 'Indonesia'),
('RTO', 'Budiarto', 'Tangerang', 'Indonesia'),
('RUF', 'Yuruf Airport', 'Yuruf', 'Indonesia'),
('RXS', 'Roxas Airport', 'Roxas', 'Philippines'),
('RZS', 'Sawan Airport', 'Sawan', 'Indonesia'),
('SAU', 'Tardamu', 'Sawu', 'Indonesia'),
('SBG', 'Maimun Saleh', 'Sabang', 'Indonesia'),
('SBW', 'Sibu Airport', 'Sibu', 'Malaysia'),
('SDJ', 'Sendai Airport', 'Sendai', 'Japan'),
('SDK', 'Sandakan Airport', 'Sandakan', 'Malaysia'),
('SEH', 'Senggeh Airport', 'Senggeh', 'Indonesia'),
('SFE', 'San Fernando Airport', 'San Fernando', 'Philippines'),
('SGN', 'Tan Son Nhat International Airport', 'Ho Chi Minh City', 'Vietnam'),
('SGQ', 'Sangkimah', 'Sangata', 'Indonesia'),
('SGZ', 'Songkhla Airport', 'Songkhla', 'Thailand'),
('SHM', 'Nanki-Shirahama Airport', 'Shirahama', 'Japan'),
('SIN', 'Singapore Changi', 'Singapore', 'Singapore'),
('SIQ', 'Dabo', 'Singkep', 'Indonesia'),
('SJI', 'San Jose Airport', 'San Jose (Antique)', 'Philippines'),
('SMM', 'Semporna Airport', 'Semporna', 'Malaysia'),
('SMQ', 'H.Asan', 'Sampit', 'Indonesia'),
('SNO', 'Sakon Nakhon Airport', 'Sakon Nakhon', 'Thailand'),
('SNW', 'Thandwe Airport', 'Thandwe', 'Myanmar'),
('SOA', 'Sóc Trăng Airport', 'Sóc Trăng', 'Vietnam'),
('SOC', 'Adi Sumarmo', 'Sukarta', 'Indonesia'),
('SPE', 'Sepulot Airport', 'Sepulot', 'Malaysia'),
('SPT', 'Sipitang Airport', 'Sipitang', 'Malaysia'),
('SQH', 'Na San Airport', 'Son-La', 'Vietnam'),
('SQN', 'Emalamo', 'Sanana', 'Indonesia'),
('SQR', 'Soroako Airport', 'Soroako', 'Indonesia'),
('SRG', 'Ahmad Yani', 'Semarang', 'Indonesia'),
('SUB', 'Juanda', 'Surabaya', 'Indonesia'),
('SUG', 'Surigao Airport', 'Surigao', 'Philippines'),
('SUP', 'Trunojoyo', 'Sumenep', 'Indonesia'),
('SWQ', 'Brangbiji', 'Sumbawa', 'Indonesia'),
('SWY', 'Sitiawan Airport', 'Sitiawan', 'Malaysia'),
('SXK', 'Olilit', 'Saumlaki', 'Indonesia'),
('SXS', 'Sahabat [Sahabat 16] Airport', 'Sahabat', 'Malaysia'),
('SXT', 'Sungai Tiang Airport', 'Taman Negara', 'Malaysia'),
('SZB', 'Sultan Abdul Aziz Shah International Airport', 'Kuala Lumpur', 'Malaysia'),
('TAG', 'Bohol International Airport', 'Panglao City', 'Philippines'),
('TAK', 'Takamatsu Airport', 'Takamatsu', 'Japan'),
('TAX', 'Taliabu Airport', 'Taliabu', 'Indonesia'),
('TBB', 'Dong Tac Airport', 'Tuy Hoa', 'Vietnam'),
('TBH', 'Tugdan Airport (Romblon Airport)', 'Tablas Island', 'Philippines'),
('TBM', 'Tumbang Samba Airport', 'Tumbang Samba', 'Indonesia'),
('TDG', 'Tandag Airport', 'Tandag', 'Philippines'),
('TDX', 'Trat Airport', 'Trat', 'Thailand'),
('TEL', 'Telupid Airport', 'Telupid', 'Malaysia'),
('TGA', 'Tengah Air Base', 'Tengah', 'Singapore'),
('TGC', 'Tanjung Manis Airport', 'Tanjung Manis', 'Malaysia'),
('TGG', 'Sultan Mahmud Airport', 'Kuala Terengganu', 'Malaysia'),
('THK', 'Thakhek Airport', 'Thakhek', 'Laos'),
('THL', 'Tachilek Airport', 'Tachilek', 'Myanmar'),
('THS', 'Sukhothai Airport', 'Sukhothai', 'Thailand'),
('TIM', 'Moses Kilangin', 'Tembagapura', 'Indonesia'),
('TIO', 'Tilin Airport', 'Tilin', 'Myanmar'),
('TJB', 'Raja Haji Abdullah', 'Tanjung Balai', 'Indonesia'),
('TJG', 'Tanjung Warukin Airport', 'Tanjung Warukin', 'Indonesia'),
('TJH', 'Tajima Airport', 'Toyooka', 'Japan'),
('TJQ', 'H.A.S. Hanandjoeddin', 'Tanjung Pandan', 'Indonesia'),
('TJS', 'Tanjung Harapan', 'Tanjung Selor', 'Indonesia'),
('TKG', 'Radin Inten II', 'Bandar Lampung City', 'Indonesia'),
('TKH', 'Takhli Airport', 'Takhli', 'Thailand'),
('TKN', 'Tokunoshima Airport', 'Tokunoshima', 'Japan'),
('TKS', 'Tokushima Airport', 'Tokushima', 'Japan'),
('TKT', 'Tak Airport', 'Tak', 'Thailand'),
('TLI', 'Lalos', 'Tolitoli', 'Indonesia'),
('TMC', 'Waikabubak', 'Tambolaka', 'Indonesia'),
('TMG', 'Tomanggong Airport', 'Tomanggong', 'Malaysia'),
('TMH', 'Tanahmerah Airport', 'Tanahmerah', 'Indonesia'),
('TMY', 'Tiom Airport', 'Tiom', 'Indonesia'),
('TNB', 'Tanah Grogot Airport', 'Tanah Grogot', 'Indonesia'),
('TNJ', 'Raja Haji Fisabilillah', 'Tanjung Pinang', 'Indonesia'),
('TNN', 'Tainan Airport', 'Tainan', 'Taiwan'),
('TOD', 'Tioman Island Airport', 'Tioman Island', 'Malaysia'),
('TOY', 'Toyama Airport', 'Toyama', 'Japan'),
('TPE', 'Taipei Taoyuan International Airport', 'Taipei', 'Taiwan'),
('TPG', 'Taiping (Tekah) Airport', 'Taiping', 'Malaysia'),
('TPK', 'Teuku Cut Ali', 'Tapaktuan', 'Indonesia'),
('TRA', 'Tarama Airport', 'Tarama', 'Japan'),
('TRK', 'Juwata', 'Tarakan', 'Indonesia'),
('TSA', 'Songshan Airport', 'Taipei', 'Taiwan'),
('TSJ', 'Tsushima Airport', 'Tsushima', 'Japan'),
('TST', 'Trang Airport', 'Trang', 'Thailand'),
('TSX', 'Tanjung Santan Airport', 'Tanjung Santan', 'Indonesia'),
('TSY', 'Wiriadinata', 'Tasikmalaya', 'Indonesia'),
('TTE', 'Sultan Babullah', 'Ternate', 'Indonesia'),
('TTJ', 'Tottori Airport', 'Tottori', 'Japan'),
('TTT', 'Fongnian Airport', 'Taitung', 'Taiwan'),
('TUG', 'Tuguegarao Airport', 'Tuguegarao', 'Philippines'),
('TVY', 'Dawei Airport', 'Dawei', 'Myanmar'),
('TWU', 'Tawau Airport', 'Tawau', 'Malaysia'),
('TXM', 'Teminabuan Airport', 'Teminabuan', 'Indonesia'),
('UAI', 'Suai Airport', 'Suai', 'Timor-Leste'),
('UBJ', 'Yamaguchi-Ube Airport', 'Ube', 'Japan'),
('UBP', 'Ubon Ratchathan Airport', 'Ubon Ratchathani', 'Thailand'),
('UBR', 'Ubrub Airport', 'Ubrub', 'Indonesia'),
('UDR', 'Maharana Pratap', 'Udaipur', 'Indonesia'),
('UEO', 'Kumejima Airport', 'Kumejima', 'Japan'),
('UGU', 'Bilogai Airport', 'Bilogai', 'Indonesia'),
('UIH', 'Phu Cat Airport', 'Qui Nhon', 'Vietnam'),
('UKB', 'Kobe Airport', 'Osaka', 'Japan'),
('UKY', 'Kansai Airport', 'Kyoto', 'Japan'),
('UNN', 'Ranong Airport', 'Ranong', 'Thailand'),
('UOL', 'Pogogul', 'Buol', 'Indonesia'),
('UPG', 'Sultan Hasanuddin', 'Makassar', 'Indonesia'),
('URT', 'Surat Thani Airport', 'Surat Thani', 'Thailand'),
('USM', 'Ko Samui Airport', 'Ko Samui', 'Thailand'),
('USU', 'Francisco Reyes Airport (Coran Airport)', 'Busuanga', 'Philippines'),
('UTH', 'Udon Thani Airport', 'Udon Thani', 'Thailand'),
('UTP', 'Rayong-Pattaya International Airport', 'U-tapao', 'Thailand'),
('VCA', 'Can Tho International Airport', 'Can Tho', 'Vietnam'),
('VCL', 'Chu Lai International Airport', 'Chu Lai', 'Vietnam'),
('VCS', 'Co Ong Airport', 'Con Dao Island', 'Vietnam'),
('VDH', 'Dong Hoi Airport', 'Dong Hoi', 'Vietnam'),
('VII', 'Vinh City Airport', 'Vinh City', 'Vietnam'),
('VIQ', 'Viqueque Airport', 'Viqueque', 'Timor-Leste'),
('VKG', 'Rach Gia Airport', 'Rach Gia', 'Vietnam'),
('VRC', 'Virac Airport', 'Virac', 'Philippines'),
('VTE', 'Wattay International  Airport', 'Vientiane', 'Laos'),
('VTG', 'Vung Tau Airport', 'Vung Tau', 'Vietnam'),
('WAR', 'Waris Airport', 'Waris', 'Indonesia'),
('WBA', 'Wahai', 'Seram Island', 'Indonesia'),
('WET', 'Waghete Airport', 'Waghete', 'Indonesia'),
('WGP', 'Umbu Mehang Kunda', 'Waingapu', 'Indonesia'),
('WKJ', 'Wakkanai Airport', 'Wakkanai', 'Japan'),
('WMX', 'Wamena Airport', 'Wamena', 'Indonesia'),
('WNP', 'Naga Airport', 'Naga / Pili', 'Philippines'),
('WOT', 'Wang’an Airport', 'Wang’an', 'Taiwan'),
('WSR', 'Wasior Airport', 'Wasior', 'Indonesia'),
('XIE', 'Xienglom Airport', 'Xienglom', 'Laos'),
('XKH', 'Xieng Khouang Airport', 'Phonsavan', 'Laos'),
('XSP', 'Seletar Airport', 'Singapore', 'Singapore'),
('XVL', 'Vinh Long Airfield', 'Vinh Long', 'Vietnam'),
('YIA', 'New Yogyakarta Int.', 'Yogyakarta', 'Indonesia'),
('ZAM', 'Zamboanga International Airport', 'Zamboanga', 'Philippines'),
('ZBY', 'Sayaboury Airport', 'Sayaboury ', 'Laos'),
('ZEG', 'Senggo Airport', 'Senggo', 'Indonesia'),
('ZRI', 'S.Condronegoro', 'Serui', 'Indonesia'),
('ZRM', 'Orai', 'Sarmi', 'Indonesia'),
('ZVK', 'Savannakhet Airport', 'Savannakhet ', 'Laos');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `from` varchar(255) DEFAULT NULL,
  `to` varchar(255) DEFAULT NULL,
  `quiz_code` varchar(50) NOT NULL,
  `duration` int(11) DEFAULT 0,
  `num_questions` int(11) DEFAULT 0,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `title`, `from`, `to`, `quiz_code`, `duration`, `num_questions`, `created_by`, `created_at`) VALUES
(3, 'test2', 'wad', '', 'QZ-TARY77', 0, 0, 'ZZZ', '2025-11-29 18:14:10'),
(4, 'ternach', 'dsa2', '', 'QZ-9LXVZ8', 0, 0, 'ac137', '2025-11-30 08:56:03'),
(5, 'Untitled Quiz', '', '', 'QZ-XALLI7', 0, 0, 'ac137', '2025-11-30 08:56:06'),
(6, 'ternach', 'dsa2', '', 'QZ-DJ4JVL', 0, 0, 'ac137', '2025-11-30 08:56:44');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_items`
--

CREATE TABLE `quiz_items` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `item_index` int(11) NOT NULL,
  `origin_iata` varchar(10) DEFAULT NULL,
  `destination_iata` varchar(10) DEFAULT NULL,
  `adults` int(11) DEFAULT 0,
  `children` int(11) DEFAULT 0,
  `infants` int(11) DEFAULT 0,
  `flight_type` varchar(20) DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `flight_number` varchar(50) DEFAULT NULL,
  `seats` varchar(255) DEFAULT NULL,
  `travel_class` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_items`
--

INSERT INTO `quiz_items` (`id`, `quiz_id`, `item_index`, `origin_iata`, `destination_iata`, `adults`, `children`, `infants`, `flight_type`, `departure_date`, `return_date`, `flight_number`, `seats`, `travel_class`) VALUES
(3, 3, 1, 'AAP', 'CBN', 1, 0, 0, 'oneway', '2025-12-06', NULL, 'FL-QAEMF', '', 'economy'),
(4, 4, 1, 'AAP', 'AGD', 1, 0, 0, 'oneway', NULL, NULL, 'FL-1PO8T', '15c', 'economy'),
(5, 5, 1, '', '', 1, 0, 0, 'oneway', NULL, NULL, 'FL-BFNL3', '15c', 'economy'),
(6, 6, 1, 'AAP', 'ABU', 1, 0, 0, 'oneway', NULL, NULL, 'FL-NG8ZG', '', 'economy');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `suffix` varchar(10) NOT NULL,
  `section` varchar(200) DEFAULT NULL,
  `teacher_id` varchar(128) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `birthday` date DEFAULT NULL,
  `sex` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `last_name`, `middle_name`, `suffix`, `section`, `teacher_id`, `avatar`, `created_at`, `updated_at`, `birthday`, `sex`) VALUES
(23, '991', 'try', 'try', '', '', 'dsa 2', 'ac137', 'uploads/avatars/avatar_692d93e05dc5a2.20775607.jpg', '2025-11-29 01:39:34', '2025-12-01 13:10:56', '2014-05-03', 'M'),
(26, 'a10', 'Eye', 'Almond AI', '', '', 'delta', 'ac137', 'uploads/avatars/avatar_692dc8d22d2cb1.68624885.jpg', '2025-12-01 13:30:29', '2025-12-01 17:08:33', '2006-06-06', 'F'),
(29, 'SIL', 'In Love', 'Still', '', '', '', 'potoooooooo', 'uploads/avatars/avatar_692dccdb458321.00847786.jpg', '2025-12-01 17:14:03', '2025-12-01 17:24:37', '2001-01-01', 'M');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `quiz_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acc_id` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submitted_flights`
--

CREATE TABLE `submitted_flights` (
  `id` int(11) NOT NULL,
  `origin_code` varchar(10) DEFAULT NULL,
  `destination_code` varchar(10) DEFAULT NULL,
  `origin_airline` varchar(255) DEFAULT NULL,
  `destination_airline` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submitted_flights`
--

INSERT INTO `submitted_flights` (`id`, `origin_code`, `destination_code`, `origin_airline`, `destination_airline`, `submitted_at`) VALUES
(11, 'WDS', 'SDS', 'Invalid code (WDS)', 'Invalid code (SDS)', '2025-11-05 11:53:24'),
(12, 'EWD', 'DWS', 'Invalid code (EWD)', 'Invalid code (DWS)', '2025-11-05 11:54:37'),
(13, 'WDS', 'WDS', 'Invalid code (WDS)', 'Invalid code (WDS)', '2025-11-05 11:56:00'),
(14, 'DDS', 'WSA', 'Invalid code (DDS)', 'Invalid code (WSA)', '2025-11-05 11:56:10'),
(15, 'WDS', 'NRT', 'Invalid code (WDS)', 'Narita International Airport', '2025-11-05 11:56:29'),
(16, 'WDS', 'VSA', 'Invalid code (WDS)', 'Invalid code (VSA)', '2025-11-05 12:01:30'),
(17, 'WDS', 'FDS', 'Invalid code (WDS)', 'Invalid code (FDS)', '2025-11-05 12:02:14'),
(18, 'NRT', 'FDS', 'Narita International Airport', 'Invalid code (FDS)', '2025-11-05 12:02:18'),
(19, 'NRT', 'FDS', 'Narita International Airport', 'Invalid code (FDS)', '2025-11-05 12:03:29'),
(20, 'NRT', 'FDS', 'Narita International Airport', 'Invalid code (FDS)', '2025-11-05 12:03:31'),
(21, 'NRT', 'FDS', 'Narita International Airport', 'Invalid code (FDS)', '2025-11-05 12:03:55'),
(22, 'DSD', 'DWS', 'Invalid code (DSD)', 'Invalid code (DWS)', '2025-11-05 12:04:01'),
(23, 'NRT', 'WSA', 'Narita International Airport', 'Invalid code (WSA)', '2025-11-05 12:04:22'),
(24, 'WD', 'DW', 'Invalid code (WD)', 'Invalid code (DW)', '2025-11-05 12:05:31'),
(25, 'DAS', 'WDA', 'Invalid code (DAS)', 'Invalid code (WDA)', '2025-11-05 12:11:41'),
(26, 'DWA', 'DSA', 'Invalid code (DWA)', 'Invalid code (DSA)', '2025-11-05 12:12:04'),
(27, 'NRT', 'DAS', 'Narita International Airport', 'Invalid code (DAS)', '2025-11-05 12:12:10'),
(28, 'NRT', 'MNL', 'Narita International Airport', 'Ninoy Aquino International Airport', '2025-11-05 12:12:18'),
(29, 'DSA', 'DES', 'Invalid code (DSA)', 'Invalid code (DES)', '2025-11-05 12:25:09'),
(30, 'MNL', 'FDS', 'Ninoy Aquino International Airport', 'Invalid code (FDS)', '2025-11-07 09:43:44'),
(31, 'AAV', 'AGV', 'Allah Valley Airport', 'Invalid code (AGV)', '2025-11-07 09:44:07'),
(32, 'MNL', 'AAV', 'Ninoy Aquino International Airport', 'Allah Valley Airport', '2025-11-07 12:00:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `airports`
--
ALTER TABLE `airports`
  ADD PRIMARY KEY (`IATACode`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_items`
--
ALTER TABLE `quiz_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_student_studentid` (`student_id`),
  ADD KEY `idx_section` (`section`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_quiz_student` (`quiz_id`,`student_id`),
  ADD KEY `idx_sub_quiz` (`quiz_id`),
  ADD KEY `idx_sub_student` (`student_id`),
  ADD KEY `acc_id` (`acc_id`);

--
-- Indexes for table `submitted_flights`
--
ALTER TABLE `submitted_flights`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `quiz_items`
--
ALTER TABLE `quiz_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `submitted_flights`
--
ALTER TABLE `submitted_flights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `quiz_items`
--
ALTER TABLE `quiz_items`
  ADD CONSTRAINT `quiz_items_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
