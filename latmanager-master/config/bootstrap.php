<?php

// Désactiver les messages de dépréciation en production
if (getenv('APP_ENV') === 'prod') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
} 