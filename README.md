## INNOVISION

# System Architecture & Codebase Analysis

## Overview
The application is a **Monolithic Web Application** built using the **Laravel** framework (PHP) for the backend and **React** (TypeScript) for the frontend, glued together by **Inertia.js**. This architecture allows for a modern Single Page Application (SPA) feel while keeping the routing and controller logic within the Laravel backend.

## Technology Stack

### Backend
- **Framework**: Laravel 11.x (PHP 8.2+)
- **Authentication**: Laravel Fortify (Session-based)
- **API/Routing**: Inertia.js (Server-side routing for SPA)
- **Database**: 
  - Primary: Likely MySQL/SQLite (Standard Laravel Eloquent)
  - Secondary: **Supabase** (Postgres) - Integrated on the client-side.

### Frontend
- **Framework**: React 19 (TypeScript)
- **Build Tool**: Vite
- **Styling**: Tailwind CSS v4
- **UI Components**: Radix UI (Headless UI), Lucide React (Icons)
- **State/Data Fetching**: Inertia.js (Props from backend), Supabase Client (Direct DB/Auth access)

## External Services & Integrations

Based on configuration files ([config/services.php](cci:7://file:///c:/Users/tedju/OneDrive/Desktop/Repository/innovisionv1.1/config/services.php:0:0-0:0), [config/mail.php](cci:7://file:///c:/Users/tedju/OneDrive/Desktop/Repository/innovisionv1.1/config/mail.php:0:0-0:0)) and dependency analysis:

| Service | Purpose | Status |
| :--- | :--- | :--- |
| **Supabase** | Database / Auth / Realtime | **Active** (Client initialized in [resources/js/lib/supabase.ts](cci:7://file:///c:/Users/tedju/OneDrive/Desktop/Repository/innovisionv1.1/resources/js/lib/supabase.ts:0:0-0:0)) |
| **AWS SES** | Email Delivery | Configured (Potential) |
| **Postmark** | Email Delivery | Configured (Potential) |
| **Resend** | Email Delivery | Configured (Potential) |
| **Slack** | Notifications | Configured (Bot User) |
| **Vite** | Asset Bundling | Active (Dev & Build) |

## Authentication Architecture

The system appears to use a **Hybrid Authentication** approach:

1.  **Laravel Auth (Primary)**: 
    - Routes in [routes/web.php](cci:7://file:///c:/Users/tedju/OneDrive/Desktop/Repository/innovisionv1.1/routes/web.php:0:0-0:0) are protected by `auth` middleware.
    - Uses Laravel's session-based authentication (likely via Fortify).
    - Handles standard Login/Register flows.

2.  **Supabase Auth (Secondary/Client)**:
    - The Supabase client is initialized in [resources/js/lib/supabase.ts](cci:7://file:///c:/Users/tedju/OneDrive/Desktop/Repository/innovisionv1.1/resources/js/lib/supabase.ts:0:0-0:0).
    - Likely used for accessing Supabase Row Level Security (RLS) protected data directly from the frontend or for specific features requiring realtime capabilities.

## System Diagram

```mermaid
graph TD
    subgraph Client ["Client Side (Browser)"]
        Browser["User Browser"]
        React["React App (Inertia.js)"]
        SupabaseClient["Supabase JS Client"]
    end

    subgraph Backend ["Server Side (Laravel)"]
        WebServer["Web Server (Nginx/Apache)"]
        Laravel["Laravel Framework"]
        Router["Inertia Router"]
        Controllers["Http Controllers"]
        Fortify["Laravel Fortify Auth"]
    end

    subgraph Data ["Data Layer"]
        SQL["Primary DB (MySQL/SQLite)"]
        SupabaseDB["Supabase (Postgres)"]
    end

    subgraph External ["External Services"]
        Email["Email Providers (SES/Resend/Postmark)"]
        Slack["Slack Notifications"]
    end

    Browser -->|HTTP Request| WebServer
    WebServer --> Laravel
    Laravel --> Router
    Router --> Controllers
    Controllers -->|Eloquent ORM| SQL
    Controllers -->|Inertia Response| React
    
    React -->|Direct Data Access| SupabaseClient
    SupabaseClient -->|API/WebSocket| SupabaseDB
    
    Laravel -->|SMTP/API| Email
    Laravel -->|Webhook| Slack
    
    Fortify -.->|Session Auth| Browser
