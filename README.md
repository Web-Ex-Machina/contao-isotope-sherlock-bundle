# contao-isotope-sherlock-bundle
Sherlock LCL interface for Contao Isotope Bundle

## Nota :

### paymentWebInit :

- `Data.transactionReference` : useable only if your contract with LCL include this option. Not managed at the moment.
- `Data.seal` : Not in `Data` contrary to what the documentation states. It has to be at the same level as the `Data` & named `Seal`
- `Data.interfaceVersion` : Not in `Data` contrary to what the documentation states. It has to be at the same level as the `Data` & named `InterfaceVersion`