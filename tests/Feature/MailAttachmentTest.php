<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Mail\Mailable;
use Papercut\{Document, Page, Text};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\{PapercutManager, PendingDocument, PendingHtml, PendingOutput};
use Papercut\Laravel\Tests\TestCase;

final class MailAttachmentTest extends TestCase
{
    private function pending(): PendingDocument
    {
        return Papercut::make(Document::make(Page::a4()->body(Text::make('Attached'))));
    }

    /** A mailable built the way a real one is: attach() inside build(), which runs when the mail is rendered. */
    private function mailable(\Closure $attach): Mailable
    {
        return new class($attach) extends Mailable {
            public function __construct(private readonly \Closure $attach)
            {
            }

            public function build(): static
            {
                ($this->attach)($this);

                return $this->html('<p>Hello</p>')->subject('Test');
            }
        };
    }

    /** Builds the mailable's message and returns the mailable, its attachments now resolved. */
    private function send(\Closure $attach): Mailable
    {
        $mailable = $this->mailable($attach);
        $mailable->to('a@example.com')->render();

        return $mailable;
    }

    public function test_pdf_attaches_with_bytes_default_name_and_mime(): void
    {
        $pdf = $this->pending()->pdf();
        $sent = $this->send(fn (Mailable $m) => $m->attach($pdf));

        $this->assertTrue((fn (Mailable $m) => $m->hasAttachedData($pdf->toString(), 'document.pdf', ['mime' => 'application/pdf']))($sent));
        $this->assertStringStartsWith('%PDF-', $pdf->toString());
    }

    public function test_docx_attaches_with_bytes_default_name_and_mime(): void
    {
        $docx = $this->pending()->docx();
        $sent = $this->send(fn (Mailable $m) => $m->attach($docx));

        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $this->assertTrue((fn (Mailable $m) => $m->hasAttachedData($docx->toString(), 'document.docx', ['mime' => $mime]))($sent));
    }

    public function test_html_attaches_the_full_page(): void
    {
        $html = $this->pending()->html();
        $sent = $this->send(fn (Mailable $m) => $m->attach($html));

        $page = $html->page()->toString();
        $this->assertStringContainsString('<html', $page);
        $this->assertTrue((fn (Mailable $m) => $m->hasAttachedData($page, 'document.html', ['mime' => 'text/html; charset=UTF-8']))($sent));
    }

    public function test_custom_name_is_cleaned_like_download(): void
    {
        $pdf = $this->pending()->pdf();
        $sent = $this->send(fn (Mailable $m) => $m->attach($pdf->attachment('2026/invoice.pdf')));

        $this->assertTrue((fn (Mailable $m) => $m->hasAttachedData($pdf->toString(), '2026-invoice.pdf', ['mime' => 'application/pdf']))($sent));
    }

    public function test_built_in_formats_match_what_output_reports(): void
    {
        $doc = $this->pending();
        foreach ([$doc->pdf(), $doc->docx(), $doc->html()->page(), $doc->html()->fragment()] as $pending) {
            $attachment = $pending->toMailAttachment();
            $output = $pending->output();
            $named = new \ReflectionProperty($attachment, 'as');
            $mime = new \ReflectionProperty($attachment, 'mime');
            $this->assertSame('document.' . $output->extension(), $named->getValue($attachment));
            $this->assertSame($output->mime(), $mime->getValue($attachment));
        }
    }

    public function test_naming_an_attachment_does_not_render_until_the_mail_is_built(): void
    {
        $renders = 0;
        $pending = new PendingOutput(function () use (&$renders): \Papercut\Export\Output {
            $renders++;

            return new \Papercut\Export\Output('bytes', 'text/plain', 'txt');
        }, 'document', 'txt', 'text/plain');

        $attachment = $pending->toMailAttachment();
        $pending->attachment('named.txt');
        $this->assertSame(0, $renders);

        $sent = $this->send(fn (Mailable $m) => $m->attach($attachment));
        $this->assertTrue((fn (Mailable $m) => $m->hasAttachedData('bytes', 'document.txt', ['mime' => 'text/plain']))($sent));
        $pending->toString();
        $this->assertSame(1, $renders);
    }

    public function test_notification_mail_message_attaches_the_output(): void
    {
        $pdf = $this->pending()->pdf();
        $message = (new \Illuminate\Notifications\Messages\MailMessage())->attach($pdf);

        $this->assertCount(1, $message->rawAttachments);
        $this->assertSame('document.pdf', $message->rawAttachments[0]['name']);
        $this->assertSame($pdf->toString(), $message->rawAttachments[0]['data']);
        $this->assertSame('application/pdf', $message->rawAttachments[0]['options']['mime']);
    }

    protected function tearDown(): void
    {
        PapercutManager::flushMacros();
        PendingDocument::flushMacros();
        PendingOutput::flushMacros();
        PendingHtml::flushMacros();
        parent::tearDown();
    }

    public function test_macros_see_this_on_all_four_classes(): void
    {
        PapercutManager::macro('whoManager', fn () => $this);
        PendingDocument::macro('whoDocument', fn () => $this);
        PendingOutput::macro('whoOutput', fn () => $this);
        PendingHtml::macro('whoHtml', fn () => $this);

        $manager = app(PapercutManager::class);
        $doc = $this->pending();
        $html = $doc->html();

        $this->assertSame($manager, $manager->__call('whoManager', []));
        $this->assertSame($doc, $doc->__call('whoDocument', []));
        $this->assertSame($doc->pdf(), $doc->pdf()->__call('whoOutput', []));
        $this->assertSame($html, $html->__call('whoHtml', []));
    }
}
