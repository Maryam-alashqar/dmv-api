<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class LegalController extends Controller
{
    /**
     * Public, browser-readable page (not a JSON API) — required as the
     * "Delete account URL" / privacy policy URL for App Store & Play Store
     * submission. Combines the general privacy policy with a dedicated
     * "Account & Data Deletion" section per Apple/Google's requirements.
     */
    public function privacyPolicy()
    {
        return view('legal.privacy-policy', [
            'privacyTitleAr' => Setting::get('privacy_title_ar', config('legal.privacy_title_ar')),
            'privacyTitleEn' => Setting::get('privacy_title_en', config('legal.privacy_title_en')),
            'privacyContentAr' => $this->formatLegalText(Setting::get('privacy_content_ar', config('legal.privacy_content_ar'))),
            'privacyContentEn' => $this->formatLegalText(Setting::get('privacy_content_en', config('legal.privacy_content_en'))),
            'deletionTitleAr' => Setting::get('deletion_title_ar', config('legal.deletion_title_ar')),
            'deletionTitleEn' => Setting::get('deletion_title_en', config('legal.deletion_title_en')),
            'deletionContentAr' => $this->formatLegalText(Setting::get('deletion_content_ar', config('legal.deletion_content_ar'))),
            'deletionContentEn' => $this->formatLegalText(Setting::get('deletion_content_en', config('legal.deletion_content_en'))),
        ]);
    }

    /**
     * Turns the plain text admins type into the Settings page (numbered
     * "1. Heading" lines, "- bullet" lines, blank-line-separated paragraphs)
     * into real HTML so the public page reads like a formatted document
     * instead of a flat text dump — without changing how the content is
     * stored or edited, and without trusting admin input as raw HTML.
     */
    private function formatLegalText(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $html = '';
        $inList = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }

                continue;
            }

            if (preg_match('/^\d+\.\s*.+$/u', $line)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }

                $html .= '<h3>' . e($line) . '</h3>';

                continue;
            }

            if (str_starts_with($line, '- ')) {
                if (! $inList) {
                    $html .= '<ul>';
                    $inList = true;
                }

                $html .= '<li>' . e(substr($line, 2)) . '</li>';

                continue;
            }

            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }

            $html .= '<p>' . e($line) . '</p>';
        }

        if ($inList) {
            $html .= '</ul>';
        }

        return $html;
    }
}
