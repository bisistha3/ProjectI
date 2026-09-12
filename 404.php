<?php
// Custom 404 — page not found. No login required.
http_response_code(404);
require __DIR__ . '/404.html';
