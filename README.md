# Laravel Financial Advisor AI Agent

An AI-powered assistant for Financial Advisors that integrates with **Gmail**, **Google Calendar**, and **HubSpot CRM**.  
The agent provides a ChatGPT-like interface that can **answer questions**, **retrieve context via RAG**, and **take actions autonomously using tool calling and memory**.

---

## 🚀 Live Demo

- **App URL:** https://laravel-financial-advisor-ai-production.up.railway.app/

> ⚠️ Note: This app is configured for a **single test user** and uses OAuth test credentials.

---

## 🧠 What This Agent Can Do

### 1. Secure Authentication & Integrations
- Login with **Google OAuth**
  - Gmail read/write
  - Google Calendar read/write
- Connect a **HubSpot CRM** account via OAuth
- Secure token storage with refresh handling

---

### 2. Chat-Based AI Interface
A ChatGPT-style interface where the user can:

#### Ask questions about clients
Examples:
- *“Who mentioned their kid plays baseball?”*
- *“Why did Greg say he wanted to sell AAPL stock?”*

The agent:
- Retrieves context from **Gmail emails** and **HubSpot contacts & notes**
- Uses **RAG (Retrieval-Augmented Generation)** with vector search
- Answers only from available data (no hallucinated CRM facts)

---

### 3. RAG (Retrieval-Augmented Generation)
- Emails and HubSpot records are embedded and stored using **pgvector**
- Relevant documents are retrieved and injected into the LLM context
- Sources include:
  - Gmail message bodies
  - HubSpot contact notes

This allows the agent to reason over real historical communication.

---

### 4. Action-Oriented AI (Tool Calling)

The agent can **take actions**, not just answer questions.

Implemented tools include:
- Search HubSpot contacts
- Send emails via Gmail
- Create Google Calendar events
- Store and update long-running tasks

Example:
> *“Schedule an appointment with Sara Smith”*

The agent:
1. Finds the contact
2. Emails available calendar times
3. Waits for a response
4. Follows up or books the event
5. Logs the interaction in HubSpot

---

### 5. Agent Memory & Ongoing Instructions

Users can give **persistent instructions**, such as:
- “When someone emails me that is not in HubSpot, create a contact.”
- “When I add a calendar event, email the attendees.”

These instructions are:
- Stored in the database
- Included in every agent reasoning cycle
- Applied proactively when new events occur

---

### 6. Proactive Automation

The agent is triggered when:
- New Gmail messages arrive
- Calendar events are created
- HubSpot records change

To keep the system simple and reliable:
- **Polling** is used instead of real-time webhooks
- Each trigger prompts the agent to decide whether action is required

This avoids hard-coded workflows and keeps behavior LLM-driven.

---

## 🏗️ Architecture Overview

### Key Design Principles
- AI-first (LLM decides actions)
- Minimal hard-coded logic
- Clear separation of:
  - Reasoning
  - Tools
  - Memory
  - Knowledge (RAG)

---

## 🗄️ Database Schema (Simplified)

Core tables:
- `users`
- `integrations` (OAuth tokens)
- `emails`
- `calendar_events`
- `hubspot_contacts`
- `hubspot_notes`
- `documents` (vector embeddings)
- `agent_instructions`
- `tasks`

---

## 🛠️ Tech Stack

**Backend**
- Laravel
- PostgreSQL + pgvector
- Gemini API (LLM + embeddings)

**Frontend**
- Livewire

**Integrations**
- Gmail API
- Google Calendar API
- HubSpot CRM API

**Deployment**
- Railway (CI/CD activated)

---


## 🧪 Known Limitations (By Design)

This project intentionally limits scope to meet a strict timebox:

- Single-user only
- Polling instead of real-time webhooks
- Limited email history imported
- No background job queue optimization
- UI focused on clarity, not pixel perfection

These tradeoffs were made to prioritize **correct agent behavior and system design**.

---

## 📝 How to Run Locally

```bash
git clone this repo

cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

php artisan serve

-- Requires PostgreSQL with pgvector enabled