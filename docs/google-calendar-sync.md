# Google Calendar Sync

ProgramaConsulta can sync appointments with Google Calendar in both directions. When a provider links their Google Calendar, any changes made in either system will be reflected in the other.

## What You Need

- A working ProgramaConsulta installation with at least one service and provider set up.
- A Google account.

## Step 1: Create Google API Credentials

You need to tell Google that your ProgramaConsulta installation is allowed to access calendar data.

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and **create a new project** (or select an existing one).
2. In the project dashboard, go to **APIs & Services** > **Library** and search for **Google Calendar API**. Click on it and press **Enable**.
3. Go to **APIs & Services** > **Credentials** and click **Create Credentials** > **OAuth client ID**.
4. If prompted, fill in the **OAuth consent screen** information first.
5. Select **Web Application** as the application type and give it a name.
6. Under **Authorized JavaScript origins**, add your domain (just the domain, e.g. `http://mywebsite.com`).
7. Under **Authorized redirect URIs**, add:
   ```
   https://your-domain.com/programaconsulta/index.php/google/oauth_callback
   ```
   Replace `your-domain.com/programaconsulta` with your actual installation URL.
8. Click **Create**. Google will show you a **Client ID** and **Client Secret** — copy both.

## Step 2: Configure Credentials in ProgramaConsulta

You can now configure the Google **Client ID** and **Client Secret** directly from the ProgramaConsulta user interface (Backend **Settings** → **Google Calendar** section).

As an alternative, you can still define them in `config.php`:

```php
const GOOGLE_SYNC_FEATURE   = TRUE;
const GOOGLE_CLIENT_ID      = 'your-client-id-here';
const GOOGLE_CLIENT_SECRET  = 'your-client-secret-here';
```

## Step 3: Link a Provider's Google Calendar

1. Log in to the ProgramaConsulta backend and go to the **Calendar** page.
2. Select a provider and click **Enable Sync**.
3. A Google sign-in window will appear. Log in with the provider's Google account and grant permission.
4. The sync is now active!

## Good to Know

- Sync is triggered from the ProgramaConsulta backend or whenever appointments change.
- Each provider can only be linked to **one** Google Calendar account.
- Recurring events are supported during sync, but they cannot be created or managed directly from ProgramaConsulta.

## Useful Links

- [Google Calendar API Docs](https://developers.google.com/google-apps/calendar)

*This document applies to ProgramaConsulta v1.6.1.*

[Back](readme.md)
