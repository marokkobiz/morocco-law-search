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
                'name_en' => 'Initial interview (case content) 30 min.',
                'name_fr' => 'Premier entretien (contenu du dossier) 30 min.',
                'name_ar' => 'مقابلة أولية (محتوى القضية) 30 دقيقة',
                'description_en' => '30-minute initial interview to review your case.',
                'description_fr' => 'Entretien initial de 30 minutes pour examiner votre dossier.',
                'description_ar' => 'مقابلة أولية لمدة 30 دقيقة لمراجعة قضيتك.',
                'price' => 0,
                'price_display_en' => 'Free',
                'price_display_fr' => 'Gratuit',
                'price_display_ar' => 'مجاني',
                'notes_en' => 'Only by WhatsApp',
                'notes_fr' => 'Uniquement par WhatsApp',
                'notes_ar' => 'عبر واتساب فقط',
                'additional_notes_en' => null,
                'additional_notes_fr' => null,
                'additional_notes_ar' => null,
                'allows_office' => true,
                'allows_whatsapp' => true,
            ],
            [
                'name_en' => 'Open a case',
                'name_fr' => 'Ouvrir un dossier',
                'name_ar' => 'فتح قضية',
                'description_en' => 'Opening a legal case file with the relevant authorities.',
                'description_fr' => 'Ouverture d\'un dossier juridique auprès des autorités compétentes.',
                'description_ar' => 'فتح ملف قضية قانوني لدى السلطات المختصة.',
                'price' => 100,
                'price_display_en' => '100,00 MAD *',
                'price_display_fr' => '100,00 MAD *',
                'price_display_ar' => '100,00 درهم *',
                'notes_en' => null,
                'notes_fr' => null,
                'notes_ar' => null,
                'additional_notes_en' => null,
                'additional_notes_fr' => null,
                'additional_notes_ar' => null,
            ],
            [
                'name_en' => 'Create documents, pleadings',
                'name_fr' => 'Rédiger des documents, conclusions',
                'name_ar' => 'إعداد المستندات والمذكرات',
                'description_en' => 'Drafting of documents, contracts or pleadings.',
                'description_fr' => 'Rédaction de documents, contrats ou conclusions.',
                'description_ar' => 'إعداد المستندات والعقود أو المذكرات.',
                'price' => 150,
                'price_display_en' => '150,00 MAD',
                'price_display_fr' => '150,00 MAD',
                'price_display_ar' => '150,00 درهم',
                'notes_en' => null,
                'notes_fr' => null,
                'notes_ar' => null,
                'additional_notes_en' => null,
                'additional_notes_fr' => null,
                'additional_notes_ar' => null,
            ],
            [
                'name_en' => 'Submission to the court',
                'name_fr' => 'Dépôt au tribunal',
                'name_ar' => 'الإيداع لدى المحكمة',
                'description_en' => 'Submission of the case file to the court.',
                'description_fr' => 'Dépôt du dossier auprès du tribunal.',
                'description_ar' => 'إيداع ملف القضية لدى المحكمة.',
                'price' => 50,
                'price_display_en' => '50,00 MAD',
                'price_display_fr' => '50,00 MAD',
                'price_display_ar' => '50,00 درهم',
                'notes_en' => 'Fee to the court',
                'notes_fr' => 'Frais de tribunal',
                'notes_ar' => 'رسوم المحكمة',
                'additional_notes_en' => 'Possibly a bailiff fee',
                'additional_notes_fr' => 'Frais d\'huissier éventuels',
                'additional_notes_ar' => 'احتمال رسوم المفوض القضائي',
            ],
            [
                'name_en' => 'Tracking the case (when there are court hearings)',
                'name_fr' => 'Suivi du dossier (lorsqu\'il y a des audiences)',
                'name_ar' => 'متابعة القضية (عند وجود جلسات)',
                'description_en' => 'Monitoring the case while court hearings are scheduled.',
                'description_fr' => 'Suivi du dossier pendant les audiences prévues.',
                'description_ar' => 'متابعة القضية أثناء الجلسات المقررة.',
                'price' => 50,
                'price_display_en' => '50,00 MAD',
                'price_display_fr' => '50,00 MAD',
                'price_display_ar' => '50,00 درهم',
                'notes_en' => 'A WhatsApp message will be sent. When there is a change',
                'notes_fr' => 'Un message WhatsApp sera envoyé en cas de changement',
                'notes_ar' => 'سيتم إرسال رسالة واتساب عند حدوث أي تغيير',
                'additional_notes_en' => null,
                'additional_notes_fr' => null,
                'additional_notes_ar' => null,
            ],
            [
                'name_en' => 'Participation in court hearings represented by a legal expert',
                'name_fr' => 'Participation aux audiences représenté par un expert juridique',
                'name_ar' => 'المشاركة في الجلسات ممثلاً بواسطة خبير قانوني',
                'description_en' => 'Representation at hearings by a legal expert.',
                'description_fr' => 'Représentation aux audiences par un expert juridique.',
                'description_ar' => 'تمثيل في الجلسات بواسطة خبير قانوني.',
                'price' => 500,
                'price_display_en' => '500,00 MAD',
                'price_display_fr' => '500,00 MAD',
                'price_display_ar' => '500,00 درهم',
                'notes_en' => 'Transport fee 75.00 MAD',
                'notes_fr' => 'Frais de transport 75,00 MAD',
                'notes_ar' => 'رسوم النقل 75.00 درهم',
                'additional_notes_en' => null,
                'additional_notes_fr' => null,
                'additional_notes_ar' => null,
            ],
            [
                'name_en' => 'Participation in court hearings by a Lawyer',
                'name_fr' => 'Participation aux audiences par un avocat',
                'name_ar' => 'المشاركة في الجلسات بواسطة محامٍ',
                'description_en' => 'Representation at hearings by a lawyer.',
                'description_fr' => 'Représentation aux audiences par un avocat.',
                'description_ar' => 'تمثيل في الجلسات بواسطة محامٍ.',
                'price' => 3000,
                'price_display_en' => 'from 3.000,00 MAD',
                'price_display_fr' => 'à partir de 3.000,00 MAD',
                'price_display_ar' => 'ابتداءً من 3.000,00 درهم',
                'notes_en' => 'Depending on city/area',
                'notes_fr' => 'Selon la ville / la région',
                'notes_ar' => 'حسب المدينة / المنطقة',
                'additional_notes_en' => null,
                'additional_notes_fr' => null,
                'additional_notes_ar' => null,
            ],
        ];

        $names = array_column($services, 'name_en');

        Service::whereNotIn('name_en', $names)->delete();

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['name_en' => $service['name_en']],
                $service,
            );
        }
    }
}
