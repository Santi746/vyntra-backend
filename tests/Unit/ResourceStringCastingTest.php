<?php

namespace Tests\Unit;

use App\Http\Resources\ClubCategoryResource;
use App\Http\Resources\ClubChannelResource;
use App\Http\Resources\ClubMemberResource;
use App\Http\Resources\ClubResource;
use App\Http\Resources\ClubRoleResource;
use App\Http\Resources\DmConversationResource;
use App\Http\Resources\DmMessageResource;
use App\Http\Resources\FriendshipResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResourceStringCastingTest extends TestCase
{
    public static function resourceClasses(): array
    {
        return [
            'UserResource' => [UserResource::class, ['uuid']],
            'ClubResource' => [ClubResource::class, ['uuid', 'owner_uuid']],
            'ClubMemberResource' => [ClubMemberResource::class, ['uuid', 'user_uuid', 'club_uuid']],
            'ClubRoleResource' => [ClubRoleResource::class, ['uuid', 'club_uuid']],
            'ClubChannelResource' => [ClubChannelResource::class, ['uuid', 'category_uuid']],
            'ClubCategoryResource' => [ClubCategoryResource::class, ['uuid', 'club_uuid']],
            'MessageResource' => [MessageResource::class, ['uuid', 'client_uuid', 'channel_uuid', 'sender_uuid', 'parent_message_uuid']],
            'DmMessageResource' => [DmMessageResource::class, ['uuid', 'client_uuid', 'dm_conversation_uuid', 'sender_uuid', 'parent_message_uuid']],
            'DmConversationResource' => [DmConversationResource::class, ['uuid']],
            'FriendshipResource' => [FriendshipResource::class, ['uuid', 'friendship_uuid']],
            'NotificationResource' => [NotificationResource::class, ['uuid']],
        ];
    }

    #[DataProvider('resourceClasses')]
    public function test_resource_casts_uuids_to_string(string $class, array $uuidFields): void
    {
        $reflection = new \ReflectionMethod($class, 'toArray');
        $body = $this->getMethodBody($reflection);

        foreach ($uuidFields as $field) {
            $this->assertStringContainsString(
                "'{$field}' => (string)",
                $body,
                "{$class} debe castear '{$field}' a (string)"
            );
        }
    }

    private function getMethodBody(\ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($filename);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
