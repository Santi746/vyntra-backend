<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $uuid
 * @property string $club_channel_uuid
 * @property string $sender_uuid
 * @property string|null $parent_message_uuid
 * @property string $content
 * @property string $status
 * @property string $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\ClubChannel|null $channel
 * @property-read ChannelMessage|null $parentMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChannelMessage> $replies
 * @property-read int|null $replies_count
 * @property-read \App\Models\User|null $sender
 * @method static \Database\Factories\ChannelMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereClubChannelUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereParentMessageUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereSenderUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage withoutTrashed()
 */
	class ChannelMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string|null $banner_url
 * @property string|null $avatar_url
 * @property string $owner_uuid
 * @property string|null $category_tag
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubCategory> $categories
 * @property-read int|null $categories_count
 * @property-read \App\Models\User|null $clubOwner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubMember> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubRole> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\ClubFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereBannerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCategoryTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereOwnerUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club withoutTrashed()
 */
	class Club extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $club_uuid
 * @property string $name
 * @property int $sort_order
 * @property bool $is_private
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubChannel> $channels
 * @property-read int|null $channels_count
 * @property-read \App\Models\Club|null $club
 * @method static \Database\Factories\ClubCategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereClubUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubCategory withoutTrashed()
 */
	class ClubCategory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $category_uuid
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property int $sort_order
 * @property bool $is_private
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\ClubCategory|null $category
 * @method static \Database\Factories\ClubChannelFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereCategoryUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel withoutTrashed()
 */
	class ClubChannel extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $club_uuid
 * @property string $joined_at
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Club|null $club
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubRole> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\ClubMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereClubUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereJoinedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereUserUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMember withoutTrashed()
 */
	class ClubMember extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $club_member_uuid
 * @property string $role_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ClubMember|null $member
 * @property-read \App\Models\ClubRole|null $role
 * @method static \Database\Factories\ClubMemberRoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole whereClubMemberUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole whereRoleUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubMemberRole whereUuid($value)
 */
	class ClubMemberRole extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $club_uuid
 * @property string $name
 * @property string $color
 * @property bool $is_fixed
 * @property int $permissions
 * @property int $sort_order
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Club|null $club
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubMember> $members
 * @property-read int|null $members_count
 * @method static \Database\Factories\ClubRoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereClubUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereIsFixed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubRole withoutTrashed()
 */
	class ClubRole extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $user_one_uuid
 * @property string $user_two_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DmMessage> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\User|null $userOne
 * @property-read \App\Models\User|null $userTwo
 * @method static \Database\Factories\DmConversationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation whereUserOneUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation whereUserTwoUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmConversation withoutTrashed()
 */
	class DmConversation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $dm_conversation_uuid
 * @property string $sender_uuid
 * @property string|null $parent_message_uuid
 * @property string $content
 * @property string $status
 * @property string $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\DmConversation|null $conversation
 * @property-read DmMessage|null $parentMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DmMessage> $replies
 * @property-read int|null $replies_count
 * @property-read \App\Models\User|null $sender
 * @method static \Database\Factories\DmMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereDmConversationUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereParentMessageUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereSenderUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMessage withoutTrashed()
 */
	class DmMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $sender_uuid
 * @property string $receiver_uuid
 * @property string $status
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $receiver
 * @property-read \App\Models\User|null $sender
 * @method static \Database\Factories\FriendshipFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereReceiverUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereSenderUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Friendship withoutTrashed()
 */
	class Friendship extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $type
 * @property array<array-key, mixed>|null $data
 * @property bool $is_read
 * @property string|null $client_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $notificationReceiver
 * @method static \Database\Factories\NotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereClientUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUserUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification withoutTrashed()
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $uuid // Clave primaria
 * @property string $username
 * @property string $user_tag
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $bio
 * @property string|null $avatar_url
 * @property string|null $banner_url
 * @property string|null $location
 * @property bool $is_online
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBannerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @property string|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $appNotifications
 * @property-read int|null $app_notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubMember> $memberships
 * @property-read int|null $memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Club> $ownedClubs
 * @property-read int|null $owned_clubs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Friendship> $receivedFriendships
 * @property-read int|null $received_friendships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Friendship> $sentFriendships
 * @property-read int|null $sent_friendships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent {}
}

