<?php

declare(strict_types=1);

final class EmailNotificationService
{
    public static function build(string $type, array $data = [], string $extraCss = ''): array
    {
        $template = EmailTemplateContent::get($type, $data);

        return [
            'subject' => $template['subject'],
            'body'    => EmailLayoutRenderer::render($template, $extraCss),
        ];
    }
}