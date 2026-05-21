<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ChannelMessage;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\ClubMember;
use App\Models\ClubRole;
use App\Models\ClubMemberRole;
use App\Models\DmConversation;
use App\Models\DmMessage;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear un usuario Fijo de prueba para que tú lo uses en Insomnia
        $testUser = User::factory()->create([
            'username' => 'tester_vyntra',
            'user_tag' => 'tester-1337',
            'first_name' => 'Tester',
            'last_name' => 'Vyntra',
            'email' => 'test@example.com',
        ]);

        // 2. Crear otros 10 usuarios aleatorios en la base de datos
        $users = User::factory(10)->create();
        $users->push($testUser);

        // 3. Crear Amistades (Friendships) entre los usuarios
        // Emparejamos a algunos usuarios de forma aleatoria
        for ($i = 0; $i < 15; $i++) {
            $sender = $users->random();
            // Evitar que un usuario sea amigo de sí mismo
            $receiver = $users->where('uuid', '!=', $sender->uuid)->random();

            // Verificar que no exista ya esta combinación de amistad
            $exists = Friendship::where(function($query) use ($sender, $receiver) {
                $query->where('sender_uuid', $sender->uuid)->where('receiver_uuid', $receiver->uuid);
            })->orWhere(function($query) use ($sender, $receiver) {
                $query->where('sender_uuid', $receiver->uuid)->where('receiver_uuid', $sender->uuid);
            })->exists();

            if (!$exists) {
                Friendship::factory()->create([
                    'sender_uuid' => $sender->uuid,
                    'receiver_uuid' => $receiver->uuid,
                    'status' => fake()->randomElement(['pending', 'accepted']),
                ]);
            }
        }

        // 4. Crear Notificaciones (Notifications) para los usuarios
        foreach ($users as $user) {
            // Creamos entre 2 y 5 notificaciones para cada usuario
            Notification::factory(fake()->numberBetween(2, 5))->create([
                'user_uuid' => $user->uuid,
            ]);
        }

        // 5. Crear Conversaciones de Mensaje Directo (DMs) para el Tester y otros
        // Creamos conversaciones del Tester con 3 usuarios diferentes
        $otherUsers = $users->where('uuid', '!=', $testUser->uuid)->random(3);
        foreach ($otherUsers as $otherUser) {
            $conversation = DmConversation::factory()->create([
                'user_one_uuid' => $testUser->uuid,
                'user_two_uuid' => $otherUser->uuid,
            ]);

            // Crear mensajes de ida y vuelta en la conversación de MD
            for ($k = 0; $k < 6; $k++) {
                $sender = fake()->boolean() ? $testUser : $otherUser;
                DmMessage::factory()->create([
                    'dm_conversation_uuid' => $conversation->uuid,
                    'sender_uuid' => $sender->uuid,
                ]);
            }
        }

        // 6. Crear 3 Clubes
        $clubs = Club::factory(3)->create([
            'owner_uuid' => fn () => $users->random()->uuid,
        ]);

        // 7. Crear Miembros, Roles, Categorías, Canales y Mensajes para cada club
        foreach ($clubs as $club) {
            // 7.1 Crear Roles para el club (ej: Admin, Moderador, VIP, Miembro)
            $roles = ClubRole::factory(3)->create([
                'club_uuid' => $club->uuid,
            ]);

            // Aseguramos que el dueño del club es miembro
            $ownerMember = ClubMember::factory()->create([
                'club_uuid' => $club->uuid,
                'user_uuid' => $club->owner_uuid,
            ]);

            // Asignar el primer rol (Admin) al dueño
            ClubMemberRole::factory()->create([
                'club_member_uuid' => $ownerMember->uuid,
                'role_uuid' => $roles->first()->uuid,
            ]);

            // Hacer que otros 4 usuarios aleatorios sean miembros del club
            $clubMembersCandidates = $users->where('uuid', '!=', $club->owner_uuid)->random(4);
            foreach ($clubMembersCandidates as $candidate) {
                $member = ClubMember::factory()->create([
                    'club_uuid' => $club->uuid,
                    'user_uuid' => $candidate->uuid,
                ]);

                // 50% de probabilidad de tener un rol dentro del club
                if (fake()->boolean()) {
                    ClubMemberRole::factory()->create([
                        'club_member_uuid' => $member->uuid,
                        'role_uuid' => $roles->random()->uuid,
                    ]);
                }
            }

            // 7.2 Crear Categorías y Canales
            $categories = ClubCategory::factory(3)->create([
                'club_uuid' => $club->uuid,
            ]);

            foreach ($categories as $category) {
                $channels = ClubChannel::factory(2)->create([
                    'category_uuid' => $category->uuid,
                ]);

                foreach ($channels as $channel) {
                    if ($channel->type === 'text') {
                        // Mensajes del canal enviados por miembros de este club
                        $clubMembersUuids = ClubMember::where('club_uuid', $club->uuid)->pluck('user_uuid')->toArray();
                        
                        ChannelMessage::factory(8)->create([
                            'club_channel_uuid' => $channel->uuid,
                            'sender_uuid' => fn () => fake()->randomElement($clubMembersUuids),
                        ]);
                    }
                }
            }
        }
    }
}
