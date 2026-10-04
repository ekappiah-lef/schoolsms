<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

/**
 * Minimal replacement for the old barryvdh/laravel-dompdf facade
 * (aliased as "PDF"), built on dompdf 3, which runs on PHP 8.
 *
 *   PDF::loadView('view', $data)->setPaper('a5')->download('file.pdf');
 */
class Pdf
{
    protected $html;
    protected $paper = 'a4';
    protected $orientation = 'portrait';

    public static function loadView(string $view, array $data = []): self
    {
        return self::loadHTML(view($view, $data)->render());
    }

    public static function loadHTML(string $html): self
    {
        $pdf = new self();
        $pdf->html = $html;

        return $pdf;
    }

    public function setPaper($paper, string $orientation = 'portrait'): self
    {
        $this->paper = $paper;
        $this->orientation = $orientation;

        return $this;
    }

    public function output(): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        // Local images (school logo) are read from the public folder only.
        $options->set('chroot', [public_path(), storage_path('app/public')]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html, 'UTF-8');
        $dompdf->setPaper($this->paper, $this->orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    public function download(string $filename): Response
    {
        return $this->respond($filename, 'attachment');
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return $this->respond($filename, 'inline');
    }

    protected function respond(string $filename, string $disposition): Response
    {
        return new Response($this->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.str_replace('"', '', $filename).'"',
        ]);
    }
}
