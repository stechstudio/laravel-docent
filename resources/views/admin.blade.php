<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Docent Admin</title>
</head>
<body>
    <h1>Docent Admin</h1>
    {{-- Mount point for the Alpine admin panel (docent-admin.js), delivered by a later executor. --}}
    <div id="docent-admin"></div>
</body>
</html>
