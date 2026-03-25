# Green API Setup

## Required environment variables

Add these to `.env`:

```env
GREEN_API_URL=https://api.green-api.com
GREEN_API_INSTANCE_ID=1234567890
GREEN_API_TOKEN=your-instance-token
GREEN_API_WEBHOOK_TOKEN="Bearer my-super-secret-token"
```

## Where to get them

1. Go to `https://console.green-api.com/`
2. Create a Green API instance
3. Link the instance to a WhatsApp number by scanning the QR code in the Green API console
4. Open the instance details page
5. Copy:
    - `GREEN_API_INSTANCE_ID` from the instance ID
    - `GREEN_API_TOKEN` from the instance API token
6. Use `GREEN_API_URL=https://api.green-api.com` unless Green API gave you a different host
7. Create your own `GREEN_API_WEBHOOK_TOKEN`
    - This is your app's shared secret for webhook protection
    - Example: `Bearer my-super-secret-token`

## Configure the webhook in Green API

Set the webhook URL in the Green API instance settings to:

- Local with tunnel: `https://your-ngrok-domain.ngrok-free.app/api/green-api/webhook`
- Production: `https://your-domain.com/api/green-api/webhook`

Set the Green API webhook authorization/token to the exact same value as `GREEN_API_WEBHOOK_TOKEN`.

Enable these webhook types:

- incoming messages
- outgoing message statuses
- outgoing API messages

## Local testing

1. Run migrations:

```bash
php artisan migrate
```

2. Start the app:

```bash
composer run dev
```

3. Expose the app publicly with ngrok or Cloudflare Tunnel
4. Save the public `/api/green-api/webhook` URL in the Green API console
5. In profile settings, add WhatsApp phone numbers for your test users in international format, for example `+447700900123`
6. Send an in-app text message from user A to user B
7. Confirm the conversation shows `Mirrored to WhatsApp (sent)`
8. Reply through WhatsApp
9. Confirm the reply appears back inside the same conversation as a WhatsApp message

## Manual webhook test

You can simulate an incoming WhatsApp webhook with:

```bash
curl -X POST "https://your-public-url/api/green-api/webhook" \
  -H "Authorization: Bearer my-super-secret-token" \
  -H "Content-Type: application/json" \
  -d '{
    "typeWebhook":"incomingMessageReceived",
    "idMessage":"TEST123",
    "senderData":{"sender":"447700900111@c.us"},
    "messageData":{"textMessageData":{"textMessage":"Hello from WhatsApp"}}
  }'
```

## Automated test

Run the messaging tests with:

```bash
php artisan test --compact tests/Feature/ConversationChatTest.php
```

## Expected success state

- Outbound in-app messages get `relay_channel=whatsapp`
- `provider_status` becomes `sent`, then may update to `delivered` or `read`
- Incoming webhook messages are stored as `channel=whatsapp` in the conversation

## Current matching behavior

Inbound WhatsApp messages are matched by sender phone number and the latest conversation that already has WhatsApp relay activity for that user.

This is suitable for one active WhatsApp-linked thread per participant. If you need strict mapping across multiple simultaneous conversations, add an explicit external thread/chat identifier to each conversation.
