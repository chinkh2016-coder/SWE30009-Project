<?php
// Load the list of eight models.
require __DIR__ . '/models.php';

// Escape text before displaying it in HTML.
function escapeHtml($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AI Gateway Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1e293b;
        }

        main {
            max-width: 1100px;
            margin: auto;
        }

        h1 {
            margin-bottom: 10px;
        }

        .description {
            color: #475569;
            line-height: 1.6;
        }

        .panel {
            margin-top: 24px;
            padding: 24px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
        }

        input[type="number"] {
            width: 160px;
            padding: 10px;
            border: 1px solid #94a3b8;
            border-radius: 8px;
            font-size: 16px;
        }

        input:focus-visible, button:focus-visible {
            outline: 3px solid #60a5fa;
            outline-offset: 3px;
        }

        .batch-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .note {
            margin-top: 20px;
            color: #475569;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        button {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .calculate-button {
            background: #2563eb;
            color: white;
        }

        .calculate-button:hover {
            background: #1d4ed8;
        }

        .reset-button {
            background: #e2e8f0;
            color: #1e293b;
        }
    </style>
</head>

<body>
<main>
    <h1>AI Gateway Dashboard</h1>

    <p class="description">
        Enter your token usage for each model.
        Select Batch API for a 50% discount on that model's usage.
        A 6% SST charge will be added after discounts.
    </p>

    <section class="panel">
        <h2>Model Usage</h2>

        <!-- Send usage and batch selections to the calculation page. -->
        <form action="calculate.php" method="post">
            <div class="table-container">
                <table>
                    <caption class="description">
                        Coursework demonstration using fictional models
                        and prices. All prices are in RM per 1,000 tokens.
                    </caption>

                    <thead>
                    <tr>
                        <th scope="col">Model</th>
                        <th scope="col">Provider</th>
                        <th scope="col">Price / 1,000 tokens</th>
                        <th scope="col">Usage (tokens)</th>
                        <th scope="col">Batch API</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($models as $model): ?>
                        <tr>
                            <th scope="row">
                                <?= escapeHtml($model['name']) ?>
                            </th>

                            <td>
                                <?= escapeHtml($model['provider']) ?>
                            </td>

                            <td>
                                RM <?= number_format($model['price'], 2) ?>
                            </td>

                            <td>
                                <!-- Only whole, non-negative token counts. -->
                                <input
                                    type="number"
                                    id="usage_<?= escapeHtml($model['id']) ?>"
                                    name="usage[<?= escapeHtml($model['id']) ?>]"
                                    value="0"
                                    min="0"
                                    max="1000000000"
                                    step="1"
                                    required
                                    aria-label="<?= escapeHtml($model['name']) ?> token usage"
                                >
                            </td>

                            <td>
                                <label class="batch-option">
                                    <input
                                        type="checkbox"
                                        id="batch_<?= escapeHtml($model['id']) ?>"
                                        name="batch[<?= escapeHtml($model['id']) ?>]"
                                        value="1"
                                    >
                                    <span>
                                        50% off
                                        <span hidden>
                                            <?= escapeHtml($model['name']) ?>
                                        </span>
                                    </span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="note">
                Enter 0 for unused models.
                Maximum usage per model: 1,000,000,000 tokens.
                Batch discounts apply only to selected models.
            </p>

            <div class="actions">
                <button
                    type="submit"
                    name="submit_button"
                    class="calculate-button"
                >
                    Calculate Usage
                </button>

                <button type="reset" class="reset-button">
                    Reset
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>