# Criteria options (live)

Source: live `GET /visaservices/searchvisa` DOM (R2).

## FIRST_VALUE_CRITERIA_OPTIONS

| Value (name) | Label |
|---|---|
| `VisaNo` | Visa number |
| `PassPortNo` | Passport number |
| `AppNo` | Application / order number |
| `MohNo` | Hajj Ministry reference number |
| `fName` | First name |

## SECOND_VALUE_CRITERIA_OPTIONS

Same option set as first. First and second selectors must differ.

## Defaults on page load

- First: `VisaNo`
- Second: `AppNo`

## Used for authorized Umrah sample (names only)

| Key | Value |
|---|---|
| AUTHORIZED_SAMPLE_FIRST_CRITERION | `PassPortNo` (Passport number) |
| AUTHORIZED_SAMPLE_SECOND_CRITERION | `VisaNo` (Visa number) |
| NATIONALITY_REQUIRED | **YES** |
