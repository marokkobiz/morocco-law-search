<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name_en' => 'Initial Consultation',
                'name_fr' => 'Consultation initiale',
                'name_ar' => 'الاستشارة الأولية',
                'description_en' => '30-min case review & legal guidance',
                'description_fr' => '30 min d\'examen du dossier et de conseils juridiques',
                'description_ar' => '30 دقيقة لمراجعة القضية والإرشاد القانوني',
                'price' => 500,
            ],
            [
                'name_en' => 'Follow-up Session',
                'name_fr' => 'Session de suivi',
                'name_ar' => 'جلسة متابعة',
                'description_en' => '30-min session on an existing case',
                'description_fr' => '30 min pour un dossier existant',
                'description_ar' => '30 دقيقة لقضية موجودة',
                'price' => 350,
            ],
            [
                'name_en' => 'Full Case Analysis',
                'name_fr' => 'Analyse complète du dossier',
                'name_ar' => 'تحليل شامل للقضية',
                'description_en' => 'Written report with legal options & next steps',
                'description_fr' => 'Rapport écrit avec options juridiques et prochaines étapes',
                'description_ar' => 'تقرير مكتوب بالخيارات القانونية والخطوات التالية',
                'price' => 1500,
            ],
            [
                'name_en' => 'Document Drafting',
                'name_fr' => 'Rédaction de documents',
                'name_ar' => 'إعداد المستندات',
                'description_en' => 'Contracts, letters, or legal notices',
                'description_fr' => 'Contrats, lettres ou mises en demeure',
                'description_ar' => 'عقود أو رسائل أو إشعارات قانونية',
                'price' => 800,
            ],
            [
                'name_en' => 'Court Representation',
                'name_fr' => 'Représentation en justice',
                'name_ar' => 'تمثيل في المحكمة',
                'description_en' => 'Lawyer attendance at hearing or meeting',
                'description_fr' => 'Présence de l\'avocat à l\'audience ou à la réunion',
                'description_ar' => 'حضور المحامي في الجلسة أو الاجتماع',
                'price' => 2500,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['name_en' => $service['name_en']],
                $service,
            );
        }
    }
}
