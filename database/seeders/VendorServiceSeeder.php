<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendorCategoryIds = Category::query()->ofType('vendor')->pluck('id', 'code');
        $serviceCategoryIds = Category::query()->ofType('service')->pluck('id', 'code');

        $vendors = [
            [
                'name' => 'Singtel',
                'category_id' => $vendorCategoryIds['telco'],
                'website' => 'https://www.singtel.com',
                'support_url' => 'https://www.singtel.com/personal/support',
                'status' => 'active',
            ],
            [
                'name' => 'Amazon Web Services',
                'category_id' => $vendorCategoryIds['cloud'],
                'website' => 'https://aws.amazon.com',
                'support_url' => 'https://aws.amazon.com/support',
                'status' => 'active',
            ],
            [
                'name' => 'Adobe',
                'category_id' => $vendorCategoryIds['saas'],
                'website' => 'https://www.adobe.com',
                'support_url' => 'https://helpx.adobe.com',
                'status' => 'active',
            ],
            [
                'name' => 'Cloudflare',
                'category_id' => $vendorCategoryIds['cloud'],
                'website' => 'https://www.cloudflare.com',
                'support_url' => 'https://support.cloudflare.com',
                'status' => 'active',
            ],
            [
                'name' => 'GitHub',
                'category_id' => $vendorCategoryIds['saas'],
                'website' => 'https://github.com',
                'support_url' => 'https://support.github.com',
                'status' => 'active',
            ],
        ];

        $services = [
            'Singtel' => [
                ['name' => 'Mobile Plan', 'plan' => 'XO 30', 'category_id' => $serviceCategoryIds['communication'], 'status' => 'active'],
                ['name' => 'Broadband', 'plan' => 'Home Fibre 2Gbps', 'category_id' => $serviceCategoryIds['communication'], 'status' => 'active'],
            ],
            'Amazon Web Services' => [
                ['name' => 'S3 Storage', 'plan' => 'Pay-as-you-go', 'category_id' => $serviceCategoryIds['storage'], 'status' => 'active'],
                ['name' => 'EC2 Compute', 'plan' => 'On-Demand', 'category_id' => $serviceCategoryIds['dev_tools'], 'status' => 'active'],
                ['name' => 'SES Email', 'plan' => 'Pay-as-you-go', 'category_id' => $serviceCategoryIds['communication'], 'status' => 'active'],
            ],
            'Adobe' => [
                ['name' => 'Creative Cloud', 'plan' => 'All Apps', 'category_id' => $serviceCategoryIds['design'], 'status' => 'active'],
            ],
            'Cloudflare' => [
                ['name' => 'DNS & CDN', 'plan' => 'Free', 'category_id' => $serviceCategoryIds['security'], 'status' => 'active'],
                ['name' => 'R2 Storage', 'plan' => 'Pay-as-you-go', 'category_id' => $serviceCategoryIds['storage'], 'status' => 'active'],
            ],
            'GitHub' => [
                ['name' => 'Copilot', 'plan' => 'Individual', 'category_id' => $serviceCategoryIds['dev_tools'], 'status' => 'active'],
                ['name' => 'Actions', 'plan' => 'Free Tier', 'category_id' => $serviceCategoryIds['dev_tools'], 'status' => 'active'],
            ],
        ];

        foreach ($vendors as $vendorData) {
            $vendor = Vendor::query()->create($vendorData);

            foreach ($services[$vendorData['name']] ?? [] as $serviceData) {
                $vendor->services()->create($serviceData);
            }
        }
    }
}
