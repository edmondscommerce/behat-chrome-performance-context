#Behat Chrome Performance  Context
## By [Edmonds Commerce](https://www.edmondscommerce.co.uk)

Chrome performance monitoring including the checking of broken links and file download cumulative sizes

### Installation

Install via composer

"edmondscommerce/behat-chrome-performance-context": "~1.1"


### Include Context in Behat Configuration

```
default:
    # ...
    suites:
        default:
            # ...
            contexts:
                - # ...
                - EdmondsCommerce\BehatChromePerformance\ChromePerformanceContext

```
