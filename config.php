<?php
// ---- EDIT THESE ----
const DB_HOST = 'localhost';
const DB_NAME = 'ecoboard_sti';
const DB_USER = 'root';
const DB_PASS = '';
const ANTHROPIC_API_KEY = '';   // optional: enables the AI assistant (otherwise built-in answers)
const AI_MODEL = 'claude-sonnet-5-5';

// ---- CARBON MODEL (editable estimates: replace with figures from your own sources) ----
const EMBODIED_KG = ['desktop'=>320,'laptop'=>250,'monitor'=>250,'keyboard'=>10,'mouse'=>5,'printer'=>120,'network'=>60,'projector'=>200,'other'=>50]; // kg CO2e to manufacture
const DEFAULT_POWER = ['desktop'=>120,'laptop'=>45,'monitor'=>30,'keyboard'=>0,'mouse'=>0,'printer'=>50,'network'=>20,'projector'=>250,'other'=>0]; // watts
const GRID_KG_PER_KWH = 0.2;     // kg CO2e per kWh of your local electricity grid
const SCHOOL_DAYS = 250;         // days of use per year
const REUSE_CREDIT = 0.30;       // share of embodied carbon avoided when a device is repaired/donated for reuse
const RECYCLE_CREDIT = 0.10;     // share avoided when properly recycled
const PLASTIC_CO2E_PER_KG = 3.0; // kg CO2e avoided per kg of plastic kept in use instead of new production
