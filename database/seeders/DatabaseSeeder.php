<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** القرص الأمني (storage/app/private) — نفس المستخدم في DocumentController. */
    private const DISK = 'private';

    public function run(): void
    {
        // 1) حساب مدير المطبعة — البيانات من ملف الإعدادات (.env / docker-compose)
        $adminPhone    = env('ADMIN_PHONE', '67793717');
        $adminPassword = env('ADMIN_PASSWORD', 'Makroom204');

        $admin = User::updateOrCreate(
            ['phone' => $adminPhone],
            [
                'name'      => 'مدير المطبعة',
                'password'  => $adminPassword,   // يُشفّر تلقائياً (cast: hashed)
                'role'      => 'admin_press',
                'is_active' => true,
            ]
        );
        $this->command->warn('════════════════════════════════════════════');
        $this->command->warn('  بيانات دخول مدير المطبعة (للاختبار المحلي):');
        $this->command->warn("  رقم الهاتف   : {$adminPhone}");
        $this->command->warn("  كلمة المرور  : {$adminPassword}");
        $this->command->warn('════════════════════════════════════════════');

        // 2) المدرسون + المراحل المخصّصة لهم + الهيكل (مرحلة > صف > مادة > هل لها ملف؟)
        $teachers = [
            [
                'name'      => 'أ. أحمد - رياضيات',
                'phone'     => '01111111111',
                'stages'    => ['الابتدائي', 'المتوسط', 'الثانوي'],
                'structure' => [
                    'الابتدائي' => [
                        'الصف الأول' => ['رياضيات' => true, 'علوم' => false],
                        'الصف الثاني' => ['رياضيات' => true],
                    ],
                    'المتوسط' => [
                        'الصف الأول المتوسط' => ['جبر' => true, 'هندسة' => false],
                    ],
                    'الثانوي' => [
                        'الصف الثالث الثانوي' => ['تفاضل وتكامل' => true, 'إحصاء' => false],
                    ],
                ],
            ],
            [
                'name'      => 'أ. محمد - فيزياء',
                'phone'     => '01222222222',
                'stages'    => ['الثانوي', 'الجامعة'],
                'structure' => [
                    'الثانوي' => [
                        'الصف الثاني الثانوي' => ['فيزياء' => true],
                        'الصف الثالث الثانوي' => ['فيزياء' => false],
                    ],
                    'الجامعة' => [
                        'المستوى الأول' => ['ميكانيكا' => true, 'كهرومغناطيسية' => false],
                    ],
                ],
            ],
            [
                'name'      => 'أ. محمود - كيمياء',
                'phone'     => '01333333333',
                'stages'    => ['المتوسط', 'الثانوي'],
                'structure' => [
                    'المتوسط' => [
                        'الصف الثالث المتوسط' => ['علوم' => true],
                    ],
                    'الثانوي' => [
                        'الصف الثاني الثانوي' => ['كيمياء عضوية' => true, 'كيمياء غير عضوية' => false],
                    ],
                ],
            ],
        ];

        foreach ($teachers as $index => $t) {
            $teacher = User::updateOrCreate(
                ['phone' => $t['phone']],
                [
                    'name'      => $t['name'],
                    'password'  => 'password123',
                    'role'      => 'teacher',
                    'stages'    => $t['stages'],
                    'is_active' => true,
                ]
            );

            // تنظيف الهيكل السابق + ملفاته (لجعل الـ Seeder قابلاً لإعادة التشغيل)
            foreach ($teacher->documents as $old) {
                Storage::disk(self::DISK)->delete($old->file_path);
            }
            $teacher->classes()->delete(); // cascade على المواد والمذكرات

            $fileCount = 0;
            foreach ($t['structure'] as $stage => $classes) {
                foreach ($classes as $className => $subjects) {
                    $class = $teacher->classes()->create(['stage' => $stage, 'name' => $className]);

                    foreach ($subjects as $subjectName => $hasFile) {
                        $subject = $class->subjects()->create(['name' => $subjectName]);

                        if ($hasFile) {
                            $storedName = Str::uuid()->toString() . '.pdf';
                            $path = "documents/{$teacher->id}/{$storedName}";

                            $pdf = $this->generateDummyPdf(
                                title:   "Sample Memo #" . ($index + 1) . "." . (++$fileCount),
                                subject: $t['name'],
                                memo:    "{$stage} / {$className} / {$subjectName}"
                            );

                            Storage::disk(self::DISK)->put($path, $pdf);

                            $subject->document()->create([
                                'user_id'       => $teacher->id,
                                'title'         => "مذكرة {$subjectName} - {$className}",
                                'file_path'     => $path,
                                'original_name' => Str::slug(Str::ascii("{$subjectName}_{$className}"), '_') . '.pdf',
                                'size'          => strlen($pdf),
                            ]);
                        }
                    }
                }
            }

            $this->command->info("✔ المدرس {$teacher->name} ({$teacher->phone}) — {$fileCount} ملف مرفوع");
        }

        // 3) مساعد تجريبي: صلاحية "طباعة فقط"، مرحلة الابتدائي، للمدرس أ. أحمد فقط
        $ahmed = User::where('phone', '01111111111')->first();
        User::updateOrCreate(
            ['phone' => '55500001'],
            [
                'name'             => 'مساعد المطبعة (طباعة/ابتدائي)',
                'password'         => 'password123',
                'role'             => 'assistant',
                'stages'           => ['الابتدائي'],
                'allowed_teachers' => [$ahmed->id],
                'scope'            => 'print',
                'is_active'        => true,
            ]
        );
        $this->command->info('✔ مساعد تجريبي: 55500001 (طباعة فقط — ابتدائي — أ. أحمد)');

        $this->command->info('✅ اكتمل إدخال البيانات التجريبية. كلمة المرور للمدرسين والمساعد: password123');
    }

    /**
     * توليد ملف PDF حقيقي وصالح (صفحة A4 واحدة) بجدول xref صحيح.
     * النص داخل الصفحة إنجليزي بسيط (خط Helvetica لا يدعم العربية) — الغرض إثبات
     * عمل الطباعة، بينما يُخزَّن العنوان العربي في قاعدة البيانات.
     */
    private function generateDummyPdf(string $title, string $subject, string $memo): string
    {
        $lines = [
            $this->escape($title),
            'Teacher: ' . $this->escape(Str::ascii($subject)),
            'Memo (AR stored in DB): ' . $this->escape(Str::ascii($memo)),
            'This is a dummy PDF for local print testing.',
            'Resolution note: upload real files at 300 DPI.',
        ];

        // بناء محتوى الصفحة (نص متعدد الأسطر)
        $content = "BT\n/F1 20 Tf\n72 780 Td\n14 TL\n";
        foreach ($lines as $i => $line) {
            $content .= "($line) Tj\n";
            $content .= "0 -28 Td\n";
        }
        $content .= "ET";

        // كائنات الـ PDF
        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
               . "/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
            4 => "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        // تجميع الملف مع حساب إزاحات xref بدقة
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /** تهريب المحارف الخاصة داخل نص الـ PDF: ( ) \ */
    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
