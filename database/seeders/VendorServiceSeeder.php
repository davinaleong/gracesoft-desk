<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = [
            [
                'name' => 'Singtel',
                'category' => 'telco',
                'website' => 'https://www.singtel.com',
                'support_url' => 'https://www.singtel.com/personal/support',
                'status' => 'active',
            ],
            [
                'name' => 'Amazon Web Services',
                'category' => 'cloud',
                'website' => 'https://aws.amazon.com',
                'support_url' => 'https://aws.amazon.com/support',
                'status' => 'active',
            ],
            [
                'name' => 'Adobe',
                'category' => 'saas',
                'website' => 'https://www.adobe.com',
                'support_url' => 'https://helpx.adobe.com',
                'status' => 'active',
            ],
            [
                'name' => 'Cloudflare',
                'category' => 'cloud',
                'website' => 'https://www.cloudflare.com',
                'support_url' => 'https://support.cloudflare.com',
                'status' => 'active',
            ],
            [
                'name' => 'GitHub',
                'category' => 'saas',
                'website' => 'https://github.com',
                'support_url' => 'https://support.github.com',
                'status' => 'active',
            ],
        ];

        $services = [
            'Singtel' => [
                ['name' => 'Mobile Plan', 'plan' => 'XO 30', 'category' => 'communication', 'status' => 'active'],
                ['name' => 'Broadband', 'plan' => 'Home Fibre 2Gbps', 'category' => 'communication', 'status' => 'active'],
            ],
            'Amazon Web Services' => [
                ['name' => 'S3 Storage', 'plan' => 'Pay-as-you-go', 'category' => 'storage', 'status' => 'active'],
                ['name' => 'EC2 Compute', 'plan' => 'On-Demand', 'category' => 'dev_tools', 'status' => 'active'],
                ['name' => 'SES Email', 'plan' => 'Pay-as-you-go', 'category' => 'communication', 'status' => 'active'],
            ],
            'Adobe' => [
                ['name' => 'Creative Cloud', 'plan' => 'All Apps', 'category' => 'design', 'status' => 'active'],
            ],
            'Cloudflare' => [
                ['name' => 'DNS & CDN', 'plan' => 'Free', 'category' => 'security', 'status' => 'active'],
                ['name' => 'R2 Storage', 'plan' => 'Pay-as-you-go', 'category' => 'storage', 'status' => 'active'],
            ],
            'GitHub' => [
                ['name' => 'Copilot', 'plan' => 'Individual', 'category' => 'dev_tools', 'status' => 'active'],
                ['name' => 'Actions', 'plan' => 'Free Tier', 'category' => 'dev_tools', 'status' => 'active'],
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
