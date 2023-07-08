<?php declare(strict_types=1);

namespace danog\MadelineProto\EventHandler;

use danog\MadelineProto\EventHandler\Keyboard\InlineKeyboard;
use danog\MadelineProto\EventHandler\Keyboard\ReplyKeyboard;
use danog\MadelineProto\EventHandler\Media\Audio;
use danog\MadelineProto\EventHandler\Media\Document;
use danog\MadelineProto\EventHandler\Media\DocumentPhoto;
use danog\MadelineProto\EventHandler\Media\Gif;
use danog\MadelineProto\EventHandler\Media\MaskSticker;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\RoundVideo;
use danog\MadelineProto\EventHandler\Media\Sticker;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Media\Voice;
use danog\MadelineProto\MTProto;
use danog\MadelineProto\StrTools;

/**
 * Represents an incoming or outgoing message.
 */
abstract class Message extends AbstractMessage
{
    /** Content of the message */
    public readonly string $message;

    /** Info about a forwarded message */
    public readonly ?ForwardedInfo $fwdInfo;

    /** Bot command (if present) */
    public readonly ?string $command;
    /** @var list<string> Bot command arguments (if present) */
    public readonly ?array $commandArgs;

    /**
     * @readonly
     *
     * @var list<string> Regex matches, if a filter regex is present
     */
    public ?array $matches = null;

    /**
     * Attached media.
     *
     * @var Audio|Document|DocumentPhoto|Gif|MaskSticker|Photo|RoundVideo|Sticker|Video|Voice|null
     */
    public readonly ?Media $media;

    /** Whether this message is a sent scheduled message */
    public readonly bool $fromScheduled;

    /** If the message was generated by an inline query, ID of the bot that generated it */
    public readonly ?int $viaBotId;

    /** Last edit date of the message */
    public readonly ?int $editDate;

    /** Inline or reply keyboard. */
    public readonly InlineKeyboard|ReplyKeyboard|null $keyboard;

    /** Whether this message was [imported from a foreign chat service](https://core.telegram.org/api/import) */
    public readonly bool $imported;

    /** For Public Service Announcement messages, the PSA type */
    public readonly string $psaType;

    // Todo media (photosizes, thumbs), albums, reactions, replies, games eventually

    /** @internal */
    public function __construct(
        MTProto $API,
        array $rawMessage,
    ) {
        parent::__construct($API, $rawMessage);
        $info = $this->API->getInfo($rawMessage);

        $this->entities = $rawMessage['entities'] ?? null;
        $this->message = $rawMessage['message'];
        $this->fromScheduled = $rawMessage['from_scheduled'];
        $this->viaBotId = $rawMessage['via_bot_id'] ?? null;
        $this->editDate = $rawMessage['edit_date'] ?? null;

        $this->keyboard = isset($rawMessage['reply_markup'])
            ? Keyboard::fromRawReplyMarkup($rawMessage['reply_markup'])
            : null;

        if (isset($rawMessage['fwd_from'])) {
            $fwdFrom = $rawMessage['fwd_from'];
            $this->fwdInfo = new ForwardedInfo(
                $fwdFrom['date'],
                isset($fwdFrom['from_id'])
                    ? $this->API->getIdInternal($fwdFrom['from_id'])
                    : null,
                $fwdFrom['from_name'] ?? null,
                $fwdFrom['channel_post'] ?? null,
                $fwdFrom['post_author'] ?? null,
                isset($fwdFrom['saved_from_peer'])
                    ? $this->API->getIdInternal($fwdFrom['saved_from_peer'])
                    : null,
                $fwdFrom['saved_from_msg_id'] ?? null
            );
            $this->psaType = $fwdFrom['psa_type'] ?? null;
        } else {
            $this->fwdInfo = null;
            $this->psaType = null;
        }

        $this->media = isset($rawMessage['media'])
            ? $API->wrapMedia($rawMessage['media'])
            : null;

        if (
            $this->entities
            && $this->entities[0]['_'] === 'messageEntityBotCommand'
            && $this->entities[0]['offset'] === 0
        ) {
            $this->command = StrTools::mbSubstr(
                $this->message,
                1,
                $this->entities[0]['length']-1
            );
            $this->commandArgs = \explode(
                ' ',
                StrTools::mbSubstr($this->message, $this->entities[0]['length']+1)
            );
        } else {
            $this->command = null;
            $this->commandArgs = null;
        }
    }

    private readonly string $html;
    private readonly string $htmlTelegram;
    private readonly ?array $entities;
    /**
     * Get an HTML version of the message.
     *
     * @param bool $allowTelegramTags Whether to allow telegram-specific tags like tg-spoiler, tg-emoji, mention links and so on...
     */
    public function getHTML(bool $allowTelegramTags = false): string
    {
        if (!$this->entities) {
            return \htmlentities($this->message);
        }
        if ($allowTelegramTags) {
            return $this->htmlTelegram ??= StrTools::entitiesToHtml($this->message, $this->entities, $allowTelegramTags);
        }
        return $this->html ??= StrTools::entitiesToHtml($this->message, $this->entities, $allowTelegramTags);
    }
}
