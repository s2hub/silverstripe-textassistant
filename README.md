# Silverstripe Text Assistant

General purpose text assistant module for Silverstripe CMS. Provides UI actions in the CMS to:
- Translate individual text fields inline
- Bulk translate multiple DataObject records

Actual LLM connectivity is intentionally delegated to a separate adapter module. Currently only:
- s2hub/silverstripe-textassistant-openai (OpenAI / compatible API)

## Features

- Per-field translate button (works with standard Text / Textarea / HTML fields)
- Bulk translate admin action (list view / ModelAdmin)
- Queued Jobs integration for large batches and background jobs
- Pluggable LLM service architecture (multiple future service adapters)

## Requirements

- Silverstripe CMS ^5
- PHP ^8.1
- A LLM service module (mandatory for real LLM calls). Example:
    composer require s2hub/silverstripe-textassistant-openai


## Installation

`composer require s2hub/silverstripe-textassistant`

(Optional) then install an LLM service module:

`composer require s2hub/silverstripe-textassistant-openai`

Run dev/build:

`vendor/bin/sake dev/build`

## Configuration

Example (with OpenAI service installed):

Environment variable in .env:

`OPENAI_API_KEY="sk-..."`

## Usage

Per-field:
1. Edit a record in CMS
2. Click TextAssistant on a supported field
3. Choose source language

Bulk:
1. Go to ModelAdmin list
2. Select records
3. Choose Bulk Translate from actions
4. Pick source & target locale
5. Job is queued; progress viewable in Queued Jobs admin

## License

BSD-3-Clause. See LICENSE.
