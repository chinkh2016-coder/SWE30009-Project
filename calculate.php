<?php
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

// Redirect direct visits to the dashboard.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$usageInputs = $_POST['usage'] ?? [];
$batchInputs = $_POST['batch'] ?? [];

$errors = [];
$results = [];

$subtotal = 0;
$totalDiscount = 0;

// Check that the submitted inputs are arrays.
if (!is_array($usageInputs) || !is_array($batchInputs)) {
    $errors[] = 'Invalid form data. Please return and try again.';
} else {
    foreach ($models as $model) {
        $id = $model['id'];

        $rawUsage = $usageInputs[$id] ?? null;
        $usage = false;

        // Accept digits only, including leading zeros.
        if (is_string($rawUsage)) {
            if (preg_match('/^[0-9]+$/D', $rawUsage)) {
                // Convert values such as "033000" to "33000".
                $normalizedUsage = ltrim($rawUsage, '0');

                // Values such as "000" represent zero.
                if ($normalizedUsage === '') {
                    $normalizedUsage = '0';
                }

                // Validate the normalized integer and its range.
                $usage = filter_var(
                    $normalizedUsage,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 0,
                            'max_range' => 1000000000
                        ]
                    ]
                );
            }
        }

        // Reject missing, invalid or out-of-range usage.
        if ($usage === false) {
            $errors[] = $model['name']
                . ': enter a whole number from 0 to 1,000,000,000.';

            continue;
        }

        // Unchecked checkboxes are not included in POST data.
        $batchValue = $batchInputs[$id] ?? null;

        // A checked Batch API option must contain "1".
        if ($batchValue !== null && $batchValue !== '1') {
            $errors[] = $model['name']
                . ': invalid Batch API option.';

            continue;
        }

        $isBatch = ($batchValue === '1');

        // Exclude unused models from the usage summary.
        if ($usage === 0) {
            continue;
        }

        // Each model's price is in RM per 1,000 tokens.
        $originalCost = ($usage / 1000) * $model['price'];

        // Apply a 50% discount only to Batch API usage.
        $discount = $isBatch ? $originalCost * 0.50 : 0;

        $lineTotal = $originalCost - $discount;

        $subtotal += $originalCost;
        $totalDiscount += $discount;

        $results[] = [
            'name' => $model['name'],
            'usage' => $usage,
            'isBatch' => $isBatch,
            'originalCost' => $originalCost,
            'discount' => $discount,
            'lineTotal' => $lineTotal
        ];
    }
}

// Apply SST after deducting Batch API discounts.
// Keep full precision until rounding the final payable amount.
$afterDiscount = $subtotal - $totalDiscount;
$sst = $afterDiscount * 0.06;

$totalAmount = round(
    $afterDiscount + $sst,
    2,
    PHP_ROUND_HALF_UP
);

// Do not display a bill if any submitted input is invalid.
if (!empty($errors)) {
    http_response_code(400);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AI Gateway — Usage Summary</title>

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

        .panel {
            padding: 24px;
            margin-top: 24px;
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

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        thead th {
            background: #eff6ff;
            color: #1e40af;
        }

        tbody th {
            font-weight: normal;
        }

        .totals {
            max-width: 400px;
            margin: 24px 0 0 auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
        }

        .grand-total {
            border-top: 2px solid #cbd5e1;
            color: #1d4ed8;
            font-size: 22px;
            font-weight: bold;
        }

        .error {
            padding: 16px;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            color: #991b1b;
        }

        .error li {
            margin-bottom: 8px;
        }

        .note {
            color: #475569;
            line-height: 1.6;
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border-radius: 8px;
            text-decoration: none;
        }

        .back-link:hover {
            background: #1d4ed8;
        }

        .back-link:focus-visible {
            outline: 3px solid #60a5fa;
            outline-offset: 3px;
        }
    </style>
</head>

<body>
<main>
    <h1>Usage Summary</h1>

    <section class="panel">
        <?php if (!empty($errors)): ?>

            <!-- Display validation errors instead of a partial bill. -->
            <div class="error" role="alert">
                <h2>Please check your input</h2>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeHtml($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php else: ?>

            <h2>Models Used</h2>

            <?php if (empty($results)): ?>

                <p>No models were used. Your total is RM 0.00.</p>

            <?php else: ?>

                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th scope="col">Model</th>
                            <th scope="col">Tokens</th>
                            <th scope="col">API Type</th>
                            <th scope="col">Original Cost (RM)</th>
                            <th scope="col">Discount (RM)</th>
                            <th scope="col">After Discount (RM)</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <th scope="row">
                                    <?= escapeHtml($result['name']) ?>
                                </th>

                                <td>
                                    <?= number_format($result['usage']) ?>
                                </td>

                                <td>
                                    <?= $result['isBatch'] ? 'Batch' : 'Standard' ?>
                                </td>

                                <td>
                                    <?= number_format($result['originalCost'], 2) ?>
                                </td>

                                <td>
                                    <?= number_format($result['discount'], 2) ?>
                                </td>

                                <td>
                                    <?= number_format($result['lineTotal'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

            <div class="totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>
                        RM <?= number_format($subtotal, 2) ?>
                    </span>
                </div>

                <div class="total-row">
                    <span>Batch Discount</span>
                    <span>
                        − RM <?= number_format($totalDiscount, 2) ?>
                    </span>
                </div>

                <div class="total-row">
                    <span>After Discount</span>
                    <span>
                        RM <?= number_format($afterDiscount, 2) ?>
                    </span>
                </div>

                <div class="total-row">
                    <span>SST (6%)</span>
                    <span>
                        RM <?= number_format($sst, 2) ?>
                    </span>
                </div>

                <div class="total-row grand-total">
                    <span>Total to Pay</span>

                    <span>
                        RM
                        <!-- Keep this locator for Selenium testing. -->
                        <span id="total_amount" name="total_amount"><?= number_format($totalAmount, 2, '.', '') ?></span>
                    </span>
                </div>
            </div>

            <p class="note">
                Charges are calculated using full precision.
                The final total is rounded to two decimal places.
                Displayed amounts are rounded independently.
            </p>

        <?php endif; ?>

        <a href="index.php" class="back-link">
            Back to Dashboard
        </a>
    </section>
</main>
</body>
</html>