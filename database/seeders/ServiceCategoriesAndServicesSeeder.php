<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategoriesAndServicesSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories
        $categories = $this->createCategories();
        
        // Create services
        $this->createServices($categories);
        
        $this->command->info('✅ Seeded service categories and dental services successfully!');
    }

    private function createCategories(): array
    {
        $categories = [
            ['name' => 'Khám & Tư vấn', 'code' => 'KTV', 'icon' => 'heroicon-o-chat-bubble-left-right', 'color' => 'blue', 'sort_order' => 1],
            ['name' => 'Nội nha', 'code' => 'NH', 'icon' => 'heroicon-o-beaker', 'color' => 'danger', 'sort_order' => 2],
            ['name' => 'Phục hồi răng', 'code' => 'PH', 'icon' => 'heroicon-o-wrench-screwdriver', 'color' => 'success', 'sort_order' => 3],
            ['name' => 'Implant', 'code' => 'IMP', 'icon' => 'heroicon-o-circle-stack', 'color' => 'warning', 'sort_order' => 4],
            ['name' => 'Niềng răng', 'code' => 'NR', 'icon' => 'heroicon-o-arrows-right-left', 'color' => 'info', 'sort_order' => 5],
            ['name' => 'Tẩy trắng răng', 'code' => 'TTR', 'icon' => 'heroicon-o-sparkles', 'color' => 'primary', 'sort_order' => 6],
            ['name' => 'Nha chu', 'code' => 'NC', 'icon' => 'heroicon-o-heart', 'color' => 'rose', 'sort_order' => 7],
            ['name' => 'Nhổ răng', 'code' => 'NHO', 'icon' => 'heroicon-o-minus-circle', 'color' => 'gray', 'sort_order' => 8],
            ['name' => 'Răng sứ thẩm mỹ', 'code' => 'RSTM', 'icon' => 'heroicon-o-star', 'color' => 'amber', 'sort_order' => 9],
            ['name' => 'Điều trị trẻ em', 'code' => 'DTTE', 'icon' => 'heroicon-o-face-smile', 'color' => 'cyan', 'sort_order' => 10],
        ];

        $created = [];
        foreach ($categories as $cat) {
            $created[$cat['code']] = ServiceCategory::updateOrCreate(
                ['code' => $cat['code']], // Find by code
                $cat // Update or create with these values
            );
        }

        return $created;
    }

    private function createServices(array $categories): void
    {
        $servicesData = $this->getServicesData();

        foreach ($servicesData as $data) {
            $code = $data['category_code'];
            unset($data['category_code']);

            if (isset($categories[$code])) {
                $data['category_id'] = $categories[$code]->id;
                $data['active'] = true;
                // Use updateOrCreate to handle existing services
                Service::updateOrCreate(
                    ['code' => $data['code']], // Find by code
                    $data // Update or create with these values
                );
            }
        }
    }

    private function getServicesData(): array
    {
        return [
            // KHÁM & TƯ VẤN
            ['category_code' => 'KTV', 'name' => 'Khám tổng quát', 'code' => 'KTQ001', 'description' => 'Khám và tư vấn tổng quát tình trạng răng miệng', 'duration_minutes' => 30, 'default_price' => 100000, 'doctor_commission_rate' => 20, 'sort_order' => 1],
            ['category_code' => 'KTV', 'name' => 'Chụp X-quang răng', 'code' => 'XQ001', 'description' => 'Chụp phim X-quang để chẩn đoán', 'duration_minutes' => 15, 'default_price' => 200000, 'doctor_commission_rate' => 15, 'sort_order' => 2],
            ['category_code' => 'KTV', 'name' => 'Chụp phim Panorama', 'code' => 'PAN001', 'description' => 'Chụp toàn cảnh hàm răng', 'duration_minutes' => 20, 'default_price' => 300000, 'doctor_commission_rate' => 15, 'sort_order' => 3],
            
            // NỘI NHA
            ['category_code' => 'NH', 'name' => 'Lấy tủy răng cửa/nanh', 'code' => 'LT001', 'description' => 'Điều trị nội nha răng cửa hoặc nanh (1 chân)', 'duration_minutes' => 60, 'default_price' => 800000, 'tooth_specific' => true, 'doctor_commission_rate' => 30, 'sort_order' => 1],
            ['category_code' => 'NH', 'name' => 'Lấy tủy răng hàm nhỏ', 'code' => 'LT002', 'description' => 'Điều trị nội nha răng hàm nhỏ (2 chân)', 'duration_minutes' => 90, 'default_price' => 1200000, 'tooth_specific' => true, 'doctor_commission_rate' => 30, 'sort_order' => 2],
            ['category_code' => 'NH', 'name' => 'Lấy tủy răng hàm lớn', 'code' => 'LT003', 'description' => 'Điều trị nội nha răng hàm lớn (3-4 chân)', 'duration_minutes' => 120, 'default_price' => 1800000, 'tooth_specific' => true, 'doctor_commission_rate' => 30, 'sort_order' => 3],
            
            // PHỤC HỒI
            ['category_code' => 'PH', 'name' => 'Trám răng Composite', 'code' => 'TR001', 'description' => 'Trám răng bằng vật liệu Composite thẩm mỹ', 'duration_minutes' => 45, 'default_price' => 300000, 'tooth_specific' => true, 'doctor_commission_rate' => 25, 'sort_order' => 1],
            ['category_code' => 'PH', 'name' => 'Răng sứ kim loại', 'code' => 'RS001', 'description' => 'Bọc răng sứ kim loại', 'duration_minutes' => 60, 'default_price' => 1500000, 'tooth_specific' => true, 'doctor_commission_rate' => 20, 'sort_order' => 2],
            ['category_code' => 'PH', 'name' => 'Răng sứ Zirconia', 'code' => 'RS002', 'description' => 'Bọc răng sứ Zirconia cao cấp', 'duration_minutes' => 60, 'default_price' => 3500000, 'tooth_specific' => true, 'doctor_commission_rate' => 20, 'sort_order' => 3],
            ['category_code' => 'PH', 'name' => 'Răng sứ Emax', 'code' => 'RS003', 'description' => 'Răng sứ toàn sứ Emax thẩm mỹ cao', 'duration_minutes' => 60, 'default_price' => 4500000, 'tooth_specific' => true, 'doctor_commission_rate' => 20, 'sort_order' => 4],
            
            // IMPLANT
            ['category_code' => 'IMP', 'name' => 'Cấy Implant Osstem', 'code' => 'IMP001', 'description' => 'Cấy trụ Implant Osstem (Hàn Quốc)', 'duration_minutes' => 90, 'default_price' => 15000000, 'tooth_specific' => true, 'doctor_commission_rate' => 15, 'sort_order' => 1],
            ['category_code' => 'IMP', 'name' => 'Cấy Implant Straumann', 'code' => 'IMP002', 'description' => 'Cấy trụ Implant Straumann (Thụy Sỹ)', 'duration_minutes' => 120, 'default_price' => 35000000, 'tooth_specific' => true, 'doctor_commission_rate' => 12, 'sort_order' => 2],
            ['category_code' => 'IMP', 'name' => 'Ghép xương khi cấy Implant', 'code' => 'GX001', 'description' => 'Ghép xương tăng chiều cao xương hàm', 'duration_minutes' => 90, 'default_price' => 8000000, 'tooth_specific' => true, 'doctor_commission_rate' => 15, 'sort_order' => 3],
            
            // NIỀNG RĂNG
            ['category_code' => 'NR', 'name' => 'Niềng răng mắc cài kim loại', 'code' => 'NR001', 'description' => 'Niềng răng mắc cài kim loại cả 2 hàm', 'duration_minutes' => 60, 'default_price' => 25000000, 'doctor_commission_rate' => 18, 'sort_order' => 1],
            ['category_code' => 'NR', 'name' => 'Niềng răng mắc cài sứ', 'code' => 'NR002', 'description' => 'Niềng răng mắc cài sứ thẩm mỹ', 'duration_minutes' => 60, 'default_price' => 35000000, 'doctor_commission_rate' => 18, 'sort_order' => 2],
            ['category_code' => 'NR', 'name' => 'Niềng răng Invisalign', 'code' => 'NR003', 'description' => 'Niềng răng khay trong suốt Invisalign', 'duration_minutes' => 45, 'default_price' => 80000000, 'doctor_commission_rate' => 15, 'sort_order' => 3],
            
            // TẨY TRẮNG
            ['category_code' => 'TTR', 'name' => 'Tẩy trắng răng tại phòng khám', 'code' => 'TTR001', 'description' => 'Tẩy trắng răng bằng công nghệ Laser/Zoom', 'duration_minutes' => 90, 'default_price' => 3000000, 'doctor_commission_rate' => 25, 'sort_order' => 1],
            ['category_code' => 'TTR', 'name' => 'Tẩy trắng răng tại nhà', 'code' => 'TTR002', 'description' => 'Làm khay tẩy trắng mang về nhà', 'duration_minutes' => 45, 'default_price' => 2000000, 'doctor_commission_rate' => 25, 'sort_order' => 2],
            
            // NHA CHU
            ['category_code' => 'NC', 'name' => 'Lấy cao răng', 'code' => 'NC001', 'description' => 'Lấy cao răng toàn hàm', 'duration_minutes' => 45, 'default_price' => 500000, 'doctor_commission_rate' => 25, 'sort_order' => 1],
            ['category_code' => 'NC', 'name' => 'Điều trị viêm nha chu', 'code' => 'NC002', 'description' => 'Điều trị viêm nướu, viêm quanh răng', 'duration_minutes' => 60, 'default_price' => 1500000, 'doctor_commission_rate' => 25, 'sort_order' => 2],
            
            // NHỔ RĂNG
            ['category_code' => 'NHO', 'name' => 'Nhổ răng đơn giản', 'code' => 'NHO001', 'description' => 'Nhổ răng lung lay hoặc răng sữa', 'duration_minutes' => 20, 'default_price' => 200000, 'tooth_specific' => true, 'doctor_commission_rate' => 25, 'sort_order' => 1],
            ['category_code' => 'NHO', 'name' => 'Nhổ răng khôn mọc lệch', 'code' => 'NHO002', 'description' => 'Nhổ răng khôn mọc nghiêng/ngầm', 'duration_minutes' => 60, 'default_price' => 1500000, 'tooth_specific' => true, 'doctor_commission_rate' => 25, 'sort_order' => 2],
            ['category_code' => 'NHO', 'name' => 'Nhổ răng khôn phẫu thuật', 'code' => 'NHO003', 'description' => 'Nhổ răng khôn ngầm, cần phẫu thuật phức tạp', 'duration_minutes' => 90, 'default_price' => 3000000, 'tooth_specific' => true, 'doctor_commission_rate' => 25, 'sort_order' => 3],
            
            // RĂNG SỨ THẨM MỸ
            ['category_code' => 'RSTM', 'name' => 'Dán sứ Veneer Emax', 'code' => 'VNR001', 'description' => 'Dán mặt sứ Veneer thẩm mỹ', 'duration_minutes' => 60, 'default_price' => 6000000, 'tooth_specific' => true, 'doctor_commission_rate' => 18, 'sort_order' => 1],
            ['category_code' => 'RSTM', 'name' => 'Bọc răng sứ thẩm mỹ toàn hàm', 'code' => 'RSTM001', 'description' => 'Làm lại toàn bộ hàm răng bằng sứ', 'duration_minutes' => 180, 'default_price' => 80000000, 'doctor_commission_rate' => 15, 'sort_order' => 2],
            
            // ĐIỀU TRỊ TRẺ EM
            ['category_code' => 'DTTE', 'name' => 'Trám răng sữa', 'code' => 'TE001', 'description' => 'Trám răng sữa bị sâu', 'duration_minutes' => 30, 'default_price' => 200000, 'tooth_specific' => true, 'doctor_commission_rate' => 25, 'sort_order' => 1],
            ['category_code' => 'DTTE', 'name' => 'Lấy tủy răng sữa', 'code' => 'TE002', 'description' => 'Điều trị nội nha răng sữa', 'duration_minutes' => 45, 'default_price' => 500000, 'tooth_specific' => true, 'doctor_commission_rate' => 25, 'sort_order' => 2],
            ['category_code' => 'DTTE', 'name' => 'Bôi Fluor phòng sâu răng', 'code' => 'TE003', 'description' => 'Bôi Fluor toàn hàm cho trẻ', 'duration_minutes' => 20, 'default_price' => 300000, 'doctor_commission_rate' => 25, 'sort_order' => 3],
        ];
    }
}
