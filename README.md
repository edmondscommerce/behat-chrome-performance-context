# Behat Chrome Performance  Context
## By [Edmonds Commerce](https://www.edmondscommerce.co.uk)

Chrome performance monitoring including the checking of broken links and file download cumulative sizes

### Installation

Install via composer

"edmondscommerce/behat-chrome-performance-context": "~1.1"


### Include Context in Behat Configuration

```
default:
    # ...
    extensions:
        Behat\MinkExtension:
            sessions:
                selenium_chrome_session:
                 selenium2:
                  browser: chrome
                  capabilities:
                    extra_capabilities: { "chromeOptions": { "args": ["--start-maximized", "--test-type"], perfLoggingPrefs: { 'traceCategories': 'blink.console,disabled-by-default-devtools.timeline' } }, "loggingPrefs": { "performance": "ALL" } }
    suites:
        default:
            # ...
            contexts:
                - # ...
                - EdmondsCommerce\BehatChromePerformance\ChromePerformanceContext

```
