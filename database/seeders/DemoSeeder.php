<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run()
    {
        // Create users
        $customer = User::create([
            'name' => 'John Customer',
            'email' => 'customer@test.com',
            'phone' => '+447700900111',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $tradesperson = User::create([
            'name' => 'Mike Trades',
            'email' => 'trades@test.com',
            'phone' => '+447700900222',
            'password' => bcrypt('password'),
            'role' => 'tradesperson',
        ]);

        // Create conversation
        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'tradesperson_id' => $tradesperson->id,
        ]);

        // Seed messages
        Message::insert([
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $customer->id,
                'type' => 'user',
                'channel' => 'in_app',
                'message' => 'Hi, I need help fixing my sink.',
                'created_at' => now(),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $tradesperson->id,
                'type' => 'user',
                'channel' => 'in_app',
                'message' => 'Sure, I can help. When are you available?',
                'created_at' => now(),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => null,
                'type' => 'system',
                'channel' => 'in_app',
                'message' => 'Job matched successfully.',
                'created_at' => now(),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => null,
                'type' => 'whatsapp',
                'channel' => 'whatsapp',
                'message' => '[WhatsApp] New job matched.',
                'created_at' => now(),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => null,
                'type' => 'sms',
                'channel' => 'sms',
                'message' => '[SMS] New job matched.',
                'created_at' => now(),
            ],
        ]);
    }
}
