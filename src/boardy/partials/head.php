<?php
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Boardy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #222;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
        }
        .nav {
            background: #1A5276;
            color: white;
            padding: 16px 24px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav a, .nav span {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        .nav .brand {
            margin-right: 20px;
            font-size: 22px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        input, textarea, button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background: #1A5276;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            opacity: .95;
        }
        .error {
            color: #b00020;
            margin-top: 12px;
        }
        .muted {
            color: #666;
        }
        .post {
            border-bottom: 1px solid #ddd;
            padding: 16px 0;
        }
        .post:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
