<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonVideoRequest extends FormRequest
{
    // Checks both the file name and the actual contents, so a renamed file cannot slip through.
    public const VIDEO_RULES = ['file', 'extensions:mp4,webm,mov', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:204800'];

    public const VIDEO_MESSAGES = [
        'video.extensions' => 'The video must be an MP4, WebM or MOV file.',
        'video.mimetypes' => 'The video must be an MP4, WebM or MOV file.',
        'video.max' => 'The video must not be larger than 200 MB.',
    ];

    public function rules(): array
    {
        return ['video' => ['required', ...self::VIDEO_RULES]];
    }

    public function messages(): array
    {
        return self::VIDEO_MESSAGES;
    }
}
