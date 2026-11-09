import csv
import time
from datetime import datetime
from decimal import Decimal
from pathlib import Path

import pytest
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC


# Locate the CSV file relative to this script.
BASE_DIR = Path(__file__).resolve().parent
CSV_FILE = BASE_DIR / "test_suite.csv"

# Address of the current AI Gateway dashboard.
URL = "http://localhost/ai_gateway/index.php"


def read_test_cases():
    """Read all test cases from CSV and preserve leading zeros."""
    with CSV_FILE.open(
        mode="r",
        newline="",
        encoding="utf-8-sig"
    ) as file:
        return list(csv.DictReader(file))


TEST_CASES = read_test_cases()


class TestAIGatewayCalculation:

    @classmethod
    def setup_class(cls):
        """Run once before all test cases."""

        # Save each run in a separate folder.
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S_%f")

        cls.scr_dir = (
            BASE_DIR
            / "screenshots_ai_gateway"
            / timestamp
        )

        cls.scr_dir.mkdir(parents=True, exist_ok=True)

        # Use installed Chrome on macOS.
        # Selenium manages the appropriate ChromeDriver.
        options = webdriver.ChromeOptions()

        cls.driver = webdriver.Chrome(options=options)
        cls.driver.set_window_size(1440, 1100)

    @classmethod
    def teardown_class(cls):
        """Run once after all test cases."""

        driver = getattr(cls, "driver", None)

        if driver is not None:
            try:
                # Pause before closing the browser.
                time.sleep(2)
            finally:
                driver.quit()

    # Run the same test method once for each CSV row.
    @pytest.mark.parametrize(
        "test_case",
        TEST_CASES,
        ids=[
            f"TC{int(row['ID']):02d}"
            for row in TEST_CASES
        ]
    )
    def test_usage_calculation(self, test_case):
        """Enter model usage and verify the final total."""

        test_case_id = f"TC{int(test_case['ID']):02d}"
        expected_value = Decimal(test_case["expectedValue"])

        # Clear screenshot references from any previous test.
        self._last_screenshot = {}

        print("\n" + "=" * 55)
        print(f"Running {test_case_id}")
        print(f"Expected total = RM {expected_value:.2f}")

        try:
            # Return to the input page before every test case.
            self.driver.get(URL)

            wait = WebDriverWait(self.driver, 10)

            wait.until(
                EC.visibility_of_element_located(
                    (By.ID, "usage_model1")
                )
            )

            # Enter usage and Batch API settings for eight models.
            for number in range(1, 9):
                usage = test_case[f"usage{number}"]
                batch_value = test_case[f"batch{number}"]

                if batch_value not in ("0", "1"):
                    raise ValueError(
                        f"batch{number} must be 0 or 1."
                    )

                usage_field = self.driver.find_element(
                    By.ID,
                    f"usage_model{number}"
                )

                # Remove the default zero before entering a value.
                usage_field.clear()
                usage_field.send_keys(usage)

                batch_checkbox = self.driver.find_element(
                    By.ID,
                    f"batch_model{number}"
                )

                should_be_checked = batch_value == "1"

                if batch_checkbox.is_selected() != should_be_checked:
                    batch_checkbox.click()

                print(
                    f"Model {number}: "
                    f"usage={usage}, "
                    f"batch={'Yes' if should_be_checked else 'No'}"
                )

                # Pause so the input process can be observed.
                time.sleep(0.5)

            # Pause to show the completed form.
            time.sleep(1.5)

            submit_button = wait.until(
                EC.element_to_be_clickable(
                    (By.NAME, "submit_button")
                )
            )

            submit_button.click()

            # Wait for the final total on the result page.
            total_element = wait.until(
                EC.visibility_of_element_located(
                    (By.ID, "total_amount")
                )
            )

            computed_value = Decimal(
                total_element.text.strip().replace(",", "")
            )

            print(f"Actual total   = RM {computed_value:.2f}")

            # Show the final total before capturing the page.
            self.driver.execute_script(
                "arguments[0].scrollIntoView({block: 'center'});",
                total_element
            )

            time.sleep(1.5)

            # Compare against the independently calculated CSV value.
            assert computed_value == expected_value, (
                f"{test_case_id}: "
                f"expected RM {expected_value:.2f}, "
                f"but received RM {computed_value:.2f}"
            )

        except AssertionError:
            print(f"{test_case_id}: FAIL")

            self.custom_take_screenshot(
                test_case_id,
                "fail"
            )

            # Allow pytest to record the failed assertion.
            raise

        except Exception as error:
            print(f"{test_case_id}: ERROR")
            print(f"{type(error).__name__}: {error}")

            self.custom_take_screenshot(
                test_case_id,
                "error"
            )

            # Record the exception and continue with other test cases.
            raise

        else:
            print(f"{test_case_id}: PASS")

            self.custom_take_screenshot(
                test_case_id,
                "pass"
            )

    def custom_take_screenshot(self, test_case_id, status):
        """Save a screenshot identified by test case and status."""

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S_%f")

        filename = (
            f"{test_case_id}_{timestamp}_{status}.png"
        )

        screenshot_path = self.scr_dir / filename

        try:
            saved = self.driver.save_screenshot(
                str(screenshot_path)
            )

            if saved:
                print(f"Screenshot saved: {screenshot_path}")

                # Keep the path available for a reporting hook.
                self._last_screenshot[status] = str(
                    screenshot_path
                )
            else:
                print("Screenshot could not be saved.")

        except Exception as error:
            # Do not hide the test result if screenshot capture fails.
            print(f"Screenshot error: {error}")