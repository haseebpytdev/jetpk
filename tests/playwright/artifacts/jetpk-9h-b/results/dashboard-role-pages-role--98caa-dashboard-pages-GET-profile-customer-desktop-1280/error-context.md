# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: dashboard-role-pages.spec.ts >> role-customer dashboard pages >> GET /profile
- Location: tests\playwright\jetpk-9h-b\dashboard-role-pages.spec.ts:25:7

# Error details

```
Error: Broken images on /profile: http://jetpk.test/storage/agencies/1/branding/jetpk-qa-9hb.png
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - generic [ref=e3]:
      - link "Asif Travels portal" [ref=e4] [cursor=pointer]:
        - /url: /customer
        - img "Asif Travels" [ref=e5]
      - generic [ref=e6]:
        - link "Public site" [ref=e7] [cursor=pointer]:
          - /url: /
        - link "Profile" [ref=e8] [cursor=pointer]:
          - /url: /profile
  - generic [ref=e9]:
    - complementary "Portal navigation" [ref=e10]:
      - generic [ref=e11]:
        - generic [ref=e12]: J
        - generic [ref=e13]:
          - generic [ref=e14]: My account
          - strong [ref=e15]: JetPK Customer
          - generic [ref=e16]: Trips, payments, and support
      - navigation "Customer account" [ref=e17]:
        - link "Overview" [ref=e18] [cursor=pointer]:
          - /url: /customer
          - img [ref=e19]
          - generic [ref=e21]: Overview
        - link "My trips" [ref=e22] [cursor=pointer]:
          - /url: /customer/bookings
          - img [ref=e23]
          - generic [ref=e26]: My trips
        - link "Travelers" [ref=e27] [cursor=pointer]:
          - /url: /customer/travelers
          - img [ref=e28]
          - generic [ref=e30]: Travelers
        - link "Support" [ref=e31] [cursor=pointer]:
          - /url: /customer/support/tickets
          - img [ref=e32]
          - generic [ref=e34]: Support
        - link "Search flights" [ref=e35] [cursor=pointer]:
          - /url: /flights/search
          - img [ref=e36]
          - generic [ref=e39]: Search flights
    - main [ref=e40]:
      - generic [ref=e42]:
        - heading "Profile settings" [level=1] [ref=e43]
        - paragraph [ref=e44]: Manage your account, contact, and travel details for faster bookings.
      - generic [ref=e45]:
        - generic [ref=e46]:
          - generic [ref=e47]:
            - generic [ref=e50]: JC
            - generic [ref=e51]:
              - generic [ref=e52]:
                - heading "JetPK Customer" [level=2] [ref=e53]
                - generic [ref=e54]: Customer
              - paragraph [ref=e55]: customer@ota.demo
              - paragraph [ref=e56]: These details help prefill future bookings.
              - generic [ref=e57]:
                - generic [ref=e58] [cursor=pointer]: Upload new photo
                - button "Upload new photo" [ref=e59]
                - paragraph [ref=e60]: JPG, PNG, or WebP. Max 2 MB.
            - link "Back to dashboard" [ref=e62] [cursor=pointer]:
              - /url: /customer/bookings
          - generic [ref=e64]:
            - region "Personal information" [ref=e65]:
              - generic [ref=e66]:
                - heading "Personal information" [level=3] [ref=e67]
                - paragraph [ref=e68]: Your legal name and identity details used on bookings.
              - generic [ref=e69]:
                - generic [ref=e70]:
                  - generic [ref=e71]: Full name
                  - textbox "Full name" [ref=e72]: JetPK Customer
                - generic [ref=e73]:
                  - generic [ref=e74]: Email
                  - textbox "Email" [ref=e75]: customer@ota.demo
                - generic [ref=e76]:
                  - generic [ref=e77]: Username
                  - textbox "Username" [ref=e78]: customer
                  - paragraph [ref=e79]: You can use this username or your email address to sign in.
                - generic [ref=e80]:
                  - generic [ref=e81]: Date of birth
                  - textbox "Date of birth" [ref=e82]
                - generic [ref=e83]:
                  - generic [ref=e84]: Gender
                  - combobox "Gender" [ref=e85]:
                    - option "Select" [selected]
                    - option "Male"
                    - option "Female"
                    - option "Unspecified"
                - generic [ref=e86]:
                  - generic [ref=e87]: Nationality
                  - combobox "Nationality" [ref=e88]:
                    - option "Select country" [selected]
                    - option "Afghanistan (AF)"
                    - option "Albania (AL)"
                    - option "Algeria (DZ)"
                    - option "American Samoa (AS)"
                    - option "Andorra (AD)"
                    - option "Angola (AO)"
                    - option "Anguilla (AI)"
                    - option "Antarctica (AQ)"
                    - option "Antigua and Barbuda (AG)"
                    - option "Argentina (AR)"
                    - option "Armenia (AM)"
                    - option "Aruba (AW)"
                    - option "Australia (AU)"
                    - option "Austria (AT)"
                    - option "Azerbaijan (AZ)"
                    - option "Bahamas (BS)"
                    - option "Bahrain (BH)"
                    - option "Bangladesh (BD)"
                    - option "Barbados (BB)"
                    - option "Belarus (BY)"
                    - option "Belgium (BE)"
                    - option "Belize (BZ)"
                    - option "Benin (BJ)"
                    - option "Bermuda (BM)"
                    - option "Bhutan (BT)"
                    - option "Bolivia, Plurinational State of (BO)"
                    - option "Bonaire, Sint Eustatius and Saba (BQ)"
                    - option "Bosnia and Herzegovina (BA)"
                    - option "Botswana (BW)"
                    - option "Bouvet Island (BV)"
                    - option "Brazil (BR)"
                    - option "British Indian Ocean Territory (IO)"
                    - option "Brunei Darussalam (BN)"
                    - option "Bulgaria (BG)"
                    - option "Burkina Faso (BF)"
                    - option "Burundi (BI)"
                    - option "Cambodia (KH)"
                    - option "Cameroon (CM)"
                    - option "Canada (CA)"
                    - option "Cape Verde (CV)"
                    - option "Cayman Islands (KY)"
                    - option "Central African Republic (CF)"
                    - option "Chad (TD)"
                    - option "Chile (CL)"
                    - option "China (CN)"
                    - option "Christmas Island (CX)"
                    - option "Cocos (Keeling) Islands (CC)"
                    - option "Colombia (CO)"
                    - option "Comoros (KM)"
                    - option "Congo (CG)"
                    - option "Congo, the Democratic Republic of the (CD)"
                    - option "Cook Islands (CK)"
                    - option "Costa Rica (CR)"
                    - option "Croatia (HR)"
                    - option "Cuba (CU)"
                    - option "Curaçao (CW)"
                    - option "Cyprus (CY)"
                    - option "Czech Republic (CZ)"
                    - option "Côte d'Ivoire (CI)"
                    - option "Denmark (DK)"
                    - option "Djibouti (DJ)"
                    - option "Dominica (DM)"
                    - option "Dominican Republic (DO)"
                    - option "Ecuador (EC)"
                    - option "Egypt (EG)"
                    - option "El Salvador (SV)"
                    - option "Equatorial Guinea (GQ)"
                    - option "Eritrea (ER)"
                    - option "Estonia (EE)"
                    - option "Ethiopia (ET)"
                    - option "Falkland Islands (Malvinas) (FK)"
                    - option "Faroe Islands (FO)"
                    - option "Fiji (FJ)"
                    - option "Finland (FI)"
                    - option "France (FR)"
                    - option "French Guiana (GF)"
                    - option "French Polynesia (PF)"
                    - option "French Southern Territories (TF)"
                    - option "Gabon (GA)"
                    - option "Gambia (GM)"
                    - option "Georgia (GE)"
                    - option "Germany (DE)"
                    - option "Ghana (GH)"
                    - option "Gibraltar (GI)"
                    - option "Greece (GR)"
                    - option "Greenland (GL)"
                    - option "Grenada (GD)"
                    - option "Guadeloupe (GP)"
                    - option "Guam (GU)"
                    - option "Guatemala (GT)"
                    - option "Guernsey (GG)"
                    - option "Guinea (GN)"
                    - option "Guinea-Bissau (GW)"
                    - option "Guyana (GY)"
                    - option "Haiti (HT)"
                    - option "Heard Island and McDonald Mcdonald Islands (HM)"
                    - option "Holy See (Vatican City State) (VA)"
                    - option "Honduras (HN)"
                    - option "Hong Kong (HK)"
                    - option "Hungary (HU)"
                    - option "Iceland (IS)"
                    - option "India (IN)"
                    - option "Indonesia (ID)"
                    - option "Iran, Islamic Republic of (IR)"
                    - option "Iraq (IQ)"
                    - option "Ireland (IE)"
                    - option "Isle of Man (IM)"
                    - option "Israel (IL)"
                    - option "Italy (IT)"
                    - option "Jamaica (JM)"
                    - option "Japan (JP)"
                    - option "Jersey (JE)"
                    - option "Jordan (JO)"
                    - option "Kazakhstan (KZ)"
                    - option "Kenya (KE)"
                    - option "Kiribati (KI)"
                    - option "Korea, Democratic People's Republic of (KP)"
                    - option "Korea, Republic of (KR)"
                    - option "Kuwait (KW)"
                    - option "Kyrgyzstan (KG)"
                    - option "Lao People's Democratic Republic (LA)"
                    - option "Latvia (LV)"
                    - option "Lebanon (LB)"
                    - option "Lesotho (LS)"
                    - option "Liberia (LR)"
                    - option "Libya (LY)"
                    - option "Liechtenstein (LI)"
                    - option "Lithuania (LT)"
                    - option "Luxembourg (LU)"
                    - option "Macao (MO)"
                    - option "Macedonia, the Former Yugoslav Republic of (MK)"
                    - option "Madagascar (MG)"
                    - option "Malawi (MW)"
                    - option "Malaysia (MY)"
                    - option "Maldives (MV)"
                    - option "Mali (ML)"
                    - option "Malta (MT)"
                    - option "Marshall Islands (MH)"
                    - option "Martinique (MQ)"
                    - option "Mauritania (MR)"
                    - option "Mauritius (MU)"
                    - option "Mayotte (YT)"
                    - option "Mexico (MX)"
                    - option "Micronesia, Federated States of (FM)"
                    - option "Moldova, Republic of (MD)"
                    - option "Monaco (MC)"
                    - option "Mongolia (MN)"
                    - option "Montenegro (ME)"
                    - option "Montserrat (MS)"
                    - option "Morocco (MA)"
                    - option "Mozambique (MZ)"
                    - option "Myanmar (MM)"
                    - option "Namibia (NA)"
                    - option "Nauru (NR)"
                    - option "Nepal (NP)"
                    - option "Netherlands (NL)"
                    - option "New Caledonia (NC)"
                    - option "New Zealand (NZ)"
                    - option "Nicaragua (NI)"
                    - option "Niger (NE)"
                    - option "Nigeria (NG)"
                    - option "Niue (NU)"
                    - option "Norfolk Island (NF)"
                    - option "Northern Mariana Islands (MP)"
                    - option "Norway (NO)"
                    - option "Oman (OM)"
                    - option "Pakistan (PK)"
                    - option "Palau (PW)"
                    - option "Palestine, State of (PS)"
                    - option "Panama (PA)"
                    - option "Papua New Guinea (PG)"
                    - option "Paraguay (PY)"
                    - option "Peru (PE)"
                    - option "Philippines (PH)"
                    - option "Pitcairn (PN)"
                    - option "Poland (PL)"
                    - option "Portugal (PT)"
                    - option "Puerto Rico (PR)"
                    - option "Qatar (QA)"
                    - option "Romania (RO)"
                    - option "Russian Federation (RU)"
                    - option "Rwanda (RW)"
                    - option "Réunion (RE)"
                    - option "Saint Barthélemy (BL)"
                    - option "Saint Helena, Ascension and Tristan da Cunha (SH)"
                    - option "Saint Kitts and Nevis (KN)"
                    - option "Saint Lucia (LC)"
                    - option "Saint Martin (French part) (MF)"
                    - option "Saint Pierre and Miquelon (PM)"
                    - option "Saint Vincent and the Grenadines (VC)"
                    - option "Samoa (WS)"
                    - option "San Marino (SM)"
                    - option "Sao Tome and Principe (ST)"
                    - option "Saudi Arabia (SA)"
                    - option "Senegal (SN)"
                    - option "Serbia (RS)"
                    - option "Seychelles (SC)"
                    - option "Sierra Leone (SL)"
                    - option "Singapore (SG)"
                    - option "Sint Maarten (Dutch part) (SX)"
                    - option "Slovakia (SK)"
                    - option "Slovenia (SI)"
                    - option "Solomon Islands (SB)"
                    - option "Somalia (SO)"
                    - option "South Africa (ZA)"
                    - option "South Georgia and the South Sandwich Islands (GS)"
                    - option "South Sudan (SS)"
                    - option "Spain (ES)"
                    - option "Sri Lanka (LK)"
                    - option "Sudan (SD)"
                    - option "Suriname (SR)"
                    - option "Svalbard and Jan Mayen (SJ)"
                    - option "Swaziland (SZ)"
                    - option "Sweden (SE)"
                    - option "Switzerland (CH)"
                    - option "Syrian Arab Republic (SY)"
                    - option "Taiwan (TW)"
                    - option "Tajikistan (TJ)"
                    - option "Tanzania, United Republic of (TZ)"
                    - option "Thailand (TH)"
                    - option "Timor-Leste (TL)"
                    - option "Togo (TG)"
                    - option "Tokelau (TK)"
                    - option "Tonga (TO)"
                    - option "Trinidad and Tobago (TT)"
                    - option "Tunisia (TN)"
                    - option "Turkey (TR)"
                    - option "Turkmenistan (TM)"
                    - option "Turks and Caicos Islands (TC)"
                    - option "Tuvalu (TV)"
                    - option "Uganda (UG)"
                    - option "Ukraine (UA)"
                    - option "United Arab Emirates (AE)"
                    - option "United Kingdom (GB)"
                    - option "United States (US)"
                    - option "United States Minor Outlying Islands (UM)"
                    - option "Uruguay (UY)"
                    - option "Uzbekistan (UZ)"
                    - option "Vanuatu (VU)"
                    - option "Venezuela, Bolivarian Republic of (VE)"
                    - option "Viet Nam (VN)"
                    - option "Virgin Islands, British (VG)"
                    - option "Virgin Islands, U.S. (VI)"
                    - option "Wallis and Futuna (WF)"
                    - option "Western Sahara (EH)"
                    - option "Yemen (YE)"
                    - option "Zambia (ZM)"
                    - option "Zimbabwe (ZW)"
                    - option "Åland Islands (AX)"
            - region "Contact details" [ref=e89]:
              - generic [ref=e90]:
                - heading "Contact details" [level=3] [ref=e91]
                - paragraph [ref=e92]: How we reach you for booking updates and support.
              - generic [ref=e93]:
                - generic [ref=e94]:
                  - generic [ref=e95]: Phone
                  - textbox "Phone" [ref=e96]
                - generic [ref=e97]:
                  - generic [ref=e98]: WhatsApp
                  - textbox "WhatsApp" [ref=e99]
                - generic [ref=e100]:
                  - generic [ref=e101]: Country
                  - combobox "Country" [ref=e102]:
                    - option "Select country" [selected]
                    - option "Afghanistan (AF)"
                    - option "Albania (AL)"
                    - option "Algeria (DZ)"
                    - option "American Samoa (AS)"
                    - option "Andorra (AD)"
                    - option "Angola (AO)"
                    - option "Anguilla (AI)"
                    - option "Antarctica (AQ)"
                    - option "Antigua and Barbuda (AG)"
                    - option "Argentina (AR)"
                    - option "Armenia (AM)"
                    - option "Aruba (AW)"
                    - option "Australia (AU)"
                    - option "Austria (AT)"
                    - option "Azerbaijan (AZ)"
                    - option "Bahamas (BS)"
                    - option "Bahrain (BH)"
                    - option "Bangladesh (BD)"
                    - option "Barbados (BB)"
                    - option "Belarus (BY)"
                    - option "Belgium (BE)"
                    - option "Belize (BZ)"
                    - option "Benin (BJ)"
                    - option "Bermuda (BM)"
                    - option "Bhutan (BT)"
                    - option "Bolivia, Plurinational State of (BO)"
                    - option "Bonaire, Sint Eustatius and Saba (BQ)"
                    - option "Bosnia and Herzegovina (BA)"
                    - option "Botswana (BW)"
                    - option "Bouvet Island (BV)"
                    - option "Brazil (BR)"
                    - option "British Indian Ocean Territory (IO)"
                    - option "Brunei Darussalam (BN)"
                    - option "Bulgaria (BG)"
                    - option "Burkina Faso (BF)"
                    - option "Burundi (BI)"
                    - option "Cambodia (KH)"
                    - option "Cameroon (CM)"
                    - option "Canada (CA)"
                    - option "Cape Verde (CV)"
                    - option "Cayman Islands (KY)"
                    - option "Central African Republic (CF)"
                    - option "Chad (TD)"
                    - option "Chile (CL)"
                    - option "China (CN)"
                    - option "Christmas Island (CX)"
                    - option "Cocos (Keeling) Islands (CC)"
                    - option "Colombia (CO)"
                    - option "Comoros (KM)"
                    - option "Congo (CG)"
                    - option "Congo, the Democratic Republic of the (CD)"
                    - option "Cook Islands (CK)"
                    - option "Costa Rica (CR)"
                    - option "Croatia (HR)"
                    - option "Cuba (CU)"
                    - option "Curaçao (CW)"
                    - option "Cyprus (CY)"
                    - option "Czech Republic (CZ)"
                    - option "Côte d'Ivoire (CI)"
                    - option "Denmark (DK)"
                    - option "Djibouti (DJ)"
                    - option "Dominica (DM)"
                    - option "Dominican Republic (DO)"
                    - option "Ecuador (EC)"
                    - option "Egypt (EG)"
                    - option "El Salvador (SV)"
                    - option "Equatorial Guinea (GQ)"
                    - option "Eritrea (ER)"
                    - option "Estonia (EE)"
                    - option "Ethiopia (ET)"
                    - option "Falkland Islands (Malvinas) (FK)"
                    - option "Faroe Islands (FO)"
                    - option "Fiji (FJ)"
                    - option "Finland (FI)"
                    - option "France (FR)"
                    - option "French Guiana (GF)"
                    - option "French Polynesia (PF)"
                    - option "French Southern Territories (TF)"
                    - option "Gabon (GA)"
                    - option "Gambia (GM)"
                    - option "Georgia (GE)"
                    - option "Germany (DE)"
                    - option "Ghana (GH)"
                    - option "Gibraltar (GI)"
                    - option "Greece (GR)"
                    - option "Greenland (GL)"
                    - option "Grenada (GD)"
                    - option "Guadeloupe (GP)"
                    - option "Guam (GU)"
                    - option "Guatemala (GT)"
                    - option "Guernsey (GG)"
                    - option "Guinea (GN)"
                    - option "Guinea-Bissau (GW)"
                    - option "Guyana (GY)"
                    - option "Haiti (HT)"
                    - option "Heard Island and McDonald Mcdonald Islands (HM)"
                    - option "Holy See (Vatican City State) (VA)"
                    - option "Honduras (HN)"
                    - option "Hong Kong (HK)"
                    - option "Hungary (HU)"
                    - option "Iceland (IS)"
                    - option "India (IN)"
                    - option "Indonesia (ID)"
                    - option "Iran, Islamic Republic of (IR)"
                    - option "Iraq (IQ)"
                    - option "Ireland (IE)"
                    - option "Isle of Man (IM)"
                    - option "Israel (IL)"
                    - option "Italy (IT)"
                    - option "Jamaica (JM)"
                    - option "Japan (JP)"
                    - option "Jersey (JE)"
                    - option "Jordan (JO)"
                    - option "Kazakhstan (KZ)"
                    - option "Kenya (KE)"
                    - option "Kiribati (KI)"
                    - option "Korea, Democratic People's Republic of (KP)"
                    - option "Korea, Republic of (KR)"
                    - option "Kuwait (KW)"
                    - option "Kyrgyzstan (KG)"
                    - option "Lao People's Democratic Republic (LA)"
                    - option "Latvia (LV)"
                    - option "Lebanon (LB)"
                    - option "Lesotho (LS)"
                    - option "Liberia (LR)"
                    - option "Libya (LY)"
                    - option "Liechtenstein (LI)"
                    - option "Lithuania (LT)"
                    - option "Luxembourg (LU)"
                    - option "Macao (MO)"
                    - option "Macedonia, the Former Yugoslav Republic of (MK)"
                    - option "Madagascar (MG)"
                    - option "Malawi (MW)"
                    - option "Malaysia (MY)"
                    - option "Maldives (MV)"
                    - option "Mali (ML)"
                    - option "Malta (MT)"
                    - option "Marshall Islands (MH)"
                    - option "Martinique (MQ)"
                    - option "Mauritania (MR)"
                    - option "Mauritius (MU)"
                    - option "Mayotte (YT)"
                    - option "Mexico (MX)"
                    - option "Micronesia, Federated States of (FM)"
                    - option "Moldova, Republic of (MD)"
                    - option "Monaco (MC)"
                    - option "Mongolia (MN)"
                    - option "Montenegro (ME)"
                    - option "Montserrat (MS)"
                    - option "Morocco (MA)"
                    - option "Mozambique (MZ)"
                    - option "Myanmar (MM)"
                    - option "Namibia (NA)"
                    - option "Nauru (NR)"
                    - option "Nepal (NP)"
                    - option "Netherlands (NL)"
                    - option "New Caledonia (NC)"
                    - option "New Zealand (NZ)"
                    - option "Nicaragua (NI)"
                    - option "Niger (NE)"
                    - option "Nigeria (NG)"
                    - option "Niue (NU)"
                    - option "Norfolk Island (NF)"
                    - option "Northern Mariana Islands (MP)"
                    - option "Norway (NO)"
                    - option "Oman (OM)"
                    - option "Pakistan (PK)"
                    - option "Palau (PW)"
                    - option "Palestine, State of (PS)"
                    - option "Panama (PA)"
                    - option "Papua New Guinea (PG)"
                    - option "Paraguay (PY)"
                    - option "Peru (PE)"
                    - option "Philippines (PH)"
                    - option "Pitcairn (PN)"
                    - option "Poland (PL)"
                    - option "Portugal (PT)"
                    - option "Puerto Rico (PR)"
                    - option "Qatar (QA)"
                    - option "Romania (RO)"
                    - option "Russian Federation (RU)"
                    - option "Rwanda (RW)"
                    - option "Réunion (RE)"
                    - option "Saint Barthélemy (BL)"
                    - option "Saint Helena, Ascension and Tristan da Cunha (SH)"
                    - option "Saint Kitts and Nevis (KN)"
                    - option "Saint Lucia (LC)"
                    - option "Saint Martin (French part) (MF)"
                    - option "Saint Pierre and Miquelon (PM)"
                    - option "Saint Vincent and the Grenadines (VC)"
                    - option "Samoa (WS)"
                    - option "San Marino (SM)"
                    - option "Sao Tome and Principe (ST)"
                    - option "Saudi Arabia (SA)"
                    - option "Senegal (SN)"
                    - option "Serbia (RS)"
                    - option "Seychelles (SC)"
                    - option "Sierra Leone (SL)"
                    - option "Singapore (SG)"
                    - option "Sint Maarten (Dutch part) (SX)"
                    - option "Slovakia (SK)"
                    - option "Slovenia (SI)"
                    - option "Solomon Islands (SB)"
                    - option "Somalia (SO)"
                    - option "South Africa (ZA)"
                    - option "South Georgia and the South Sandwich Islands (GS)"
                    - option "South Sudan (SS)"
                    - option "Spain (ES)"
                    - option "Sri Lanka (LK)"
                    - option "Sudan (SD)"
                    - option "Suriname (SR)"
                    - option "Svalbard and Jan Mayen (SJ)"
                    - option "Swaziland (SZ)"
                    - option "Sweden (SE)"
                    - option "Switzerland (CH)"
                    - option "Syrian Arab Republic (SY)"
                    - option "Taiwan (TW)"
                    - option "Tajikistan (TJ)"
                    - option "Tanzania, United Republic of (TZ)"
                    - option "Thailand (TH)"
                    - option "Timor-Leste (TL)"
                    - option "Togo (TG)"
                    - option "Tokelau (TK)"
                    - option "Tonga (TO)"
                    - option "Trinidad and Tobago (TT)"
                    - option "Tunisia (TN)"
                    - option "Turkey (TR)"
                    - option "Turkmenistan (TM)"
                    - option "Turks and Caicos Islands (TC)"
                    - option "Tuvalu (TV)"
                    - option "Uganda (UG)"
                    - option "Ukraine (UA)"
                    - option "United Arab Emirates (AE)"
                    - option "United Kingdom (GB)"
                    - option "United States (US)"
                    - option "United States Minor Outlying Islands (UM)"
                    - option "Uruguay (UY)"
                    - option "Uzbekistan (UZ)"
                    - option "Vanuatu (VU)"
                    - option "Venezuela, Bolivarian Republic of (VE)"
                    - option "Viet Nam (VN)"
                    - option "Virgin Islands, British (VG)"
                    - option "Virgin Islands, U.S. (VI)"
                    - option "Wallis and Futuna (WF)"
                    - option "Western Sahara (EH)"
                    - option "Yemen (YE)"
                    - option "Zambia (ZM)"
                    - option "Zimbabwe (ZW)"
                    - option "Åland Islands (AX)"
                - generic [ref=e103]:
                  - generic [ref=e104]: City
                  - textbox "City" [ref=e105]
            - region "Travel documents" [ref=e106]:
              - generic [ref=e107]:
                - heading "Travel documents" [level=3] [ref=e108]
                - paragraph [ref=e109]: Passport and ID details speed up passenger forms.
              - generic [ref=e110]:
                - generic [ref=e111]:
                  - generic [ref=e112]: Passport number
                  - textbox "Passport number" [ref=e113]
                - generic [ref=e114]:
                  - generic [ref=e115]: Passport issuing country
                  - combobox "Passport issuing country" [ref=e116]:
                    - option "Select country" [selected]
                    - option "Afghanistan (AF)"
                    - option "Albania (AL)"
                    - option "Algeria (DZ)"
                    - option "American Samoa (AS)"
                    - option "Andorra (AD)"
                    - option "Angola (AO)"
                    - option "Anguilla (AI)"
                    - option "Antarctica (AQ)"
                    - option "Antigua and Barbuda (AG)"
                    - option "Argentina (AR)"
                    - option "Armenia (AM)"
                    - option "Aruba (AW)"
                    - option "Australia (AU)"
                    - option "Austria (AT)"
                    - option "Azerbaijan (AZ)"
                    - option "Bahamas (BS)"
                    - option "Bahrain (BH)"
                    - option "Bangladesh (BD)"
                    - option "Barbados (BB)"
                    - option "Belarus (BY)"
                    - option "Belgium (BE)"
                    - option "Belize (BZ)"
                    - option "Benin (BJ)"
                    - option "Bermuda (BM)"
                    - option "Bhutan (BT)"
                    - option "Bolivia, Plurinational State of (BO)"
                    - option "Bonaire, Sint Eustatius and Saba (BQ)"
                    - option "Bosnia and Herzegovina (BA)"
                    - option "Botswana (BW)"
                    - option "Bouvet Island (BV)"
                    - option "Brazil (BR)"
                    - option "British Indian Ocean Territory (IO)"
                    - option "Brunei Darussalam (BN)"
                    - option "Bulgaria (BG)"
                    - option "Burkina Faso (BF)"
                    - option "Burundi (BI)"
                    - option "Cambodia (KH)"
                    - option "Cameroon (CM)"
                    - option "Canada (CA)"
                    - option "Cape Verde (CV)"
                    - option "Cayman Islands (KY)"
                    - option "Central African Republic (CF)"
                    - option "Chad (TD)"
                    - option "Chile (CL)"
                    - option "China (CN)"
                    - option "Christmas Island (CX)"
                    - option "Cocos (Keeling) Islands (CC)"
                    - option "Colombia (CO)"
                    - option "Comoros (KM)"
                    - option "Congo (CG)"
                    - option "Congo, the Democratic Republic of the (CD)"
                    - option "Cook Islands (CK)"
                    - option "Costa Rica (CR)"
                    - option "Croatia (HR)"
                    - option "Cuba (CU)"
                    - option "Curaçao (CW)"
                    - option "Cyprus (CY)"
                    - option "Czech Republic (CZ)"
                    - option "Côte d'Ivoire (CI)"
                    - option "Denmark (DK)"
                    - option "Djibouti (DJ)"
                    - option "Dominica (DM)"
                    - option "Dominican Republic (DO)"
                    - option "Ecuador (EC)"
                    - option "Egypt (EG)"
                    - option "El Salvador (SV)"
                    - option "Equatorial Guinea (GQ)"
                    - option "Eritrea (ER)"
                    - option "Estonia (EE)"
                    - option "Ethiopia (ET)"
                    - option "Falkland Islands (Malvinas) (FK)"
                    - option "Faroe Islands (FO)"
                    - option "Fiji (FJ)"
                    - option "Finland (FI)"
                    - option "France (FR)"
                    - option "French Guiana (GF)"
                    - option "French Polynesia (PF)"
                    - option "French Southern Territories (TF)"
                    - option "Gabon (GA)"
                    - option "Gambia (GM)"
                    - option "Georgia (GE)"
                    - option "Germany (DE)"
                    - option "Ghana (GH)"
                    - option "Gibraltar (GI)"
                    - option "Greece (GR)"
                    - option "Greenland (GL)"
                    - option "Grenada (GD)"
                    - option "Guadeloupe (GP)"
                    - option "Guam (GU)"
                    - option "Guatemala (GT)"
                    - option "Guernsey (GG)"
                    - option "Guinea (GN)"
                    - option "Guinea-Bissau (GW)"
                    - option "Guyana (GY)"
                    - option "Haiti (HT)"
                    - option "Heard Island and McDonald Mcdonald Islands (HM)"
                    - option "Holy See (Vatican City State) (VA)"
                    - option "Honduras (HN)"
                    - option "Hong Kong (HK)"
                    - option "Hungary (HU)"
                    - option "Iceland (IS)"
                    - option "India (IN)"
                    - option "Indonesia (ID)"
                    - option "Iran, Islamic Republic of (IR)"
                    - option "Iraq (IQ)"
                    - option "Ireland (IE)"
                    - option "Isle of Man (IM)"
                    - option "Israel (IL)"
                    - option "Italy (IT)"
                    - option "Jamaica (JM)"
                    - option "Japan (JP)"
                    - option "Jersey (JE)"
                    - option "Jordan (JO)"
                    - option "Kazakhstan (KZ)"
                    - option "Kenya (KE)"
                    - option "Kiribati (KI)"
                    - option "Korea, Democratic People's Republic of (KP)"
                    - option "Korea, Republic of (KR)"
                    - option "Kuwait (KW)"
                    - option "Kyrgyzstan (KG)"
                    - option "Lao People's Democratic Republic (LA)"
                    - option "Latvia (LV)"
                    - option "Lebanon (LB)"
                    - option "Lesotho (LS)"
                    - option "Liberia (LR)"
                    - option "Libya (LY)"
                    - option "Liechtenstein (LI)"
                    - option "Lithuania (LT)"
                    - option "Luxembourg (LU)"
                    - option "Macao (MO)"
                    - option "Macedonia, the Former Yugoslav Republic of (MK)"
                    - option "Madagascar (MG)"
                    - option "Malawi (MW)"
                    - option "Malaysia (MY)"
                    - option "Maldives (MV)"
                    - option "Mali (ML)"
                    - option "Malta (MT)"
                    - option "Marshall Islands (MH)"
                    - option "Martinique (MQ)"
                    - option "Mauritania (MR)"
                    - option "Mauritius (MU)"
                    - option "Mayotte (YT)"
                    - option "Mexico (MX)"
                    - option "Micronesia, Federated States of (FM)"
                    - option "Moldova, Republic of (MD)"
                    - option "Monaco (MC)"
                    - option "Mongolia (MN)"
                    - option "Montenegro (ME)"
                    - option "Montserrat (MS)"
                    - option "Morocco (MA)"
                    - option "Mozambique (MZ)"
                    - option "Myanmar (MM)"
                    - option "Namibia (NA)"
                    - option "Nauru (NR)"
                    - option "Nepal (NP)"
                    - option "Netherlands (NL)"
                    - option "New Caledonia (NC)"
                    - option "New Zealand (NZ)"
                    - option "Nicaragua (NI)"
                    - option "Niger (NE)"
                    - option "Nigeria (NG)"
                    - option "Niue (NU)"
                    - option "Norfolk Island (NF)"
                    - option "Northern Mariana Islands (MP)"
                    - option "Norway (NO)"
                    - option "Oman (OM)"
                    - option "Pakistan (PK)"
                    - option "Palau (PW)"
                    - option "Palestine, State of (PS)"
                    - option "Panama (PA)"
                    - option "Papua New Guinea (PG)"
                    - option "Paraguay (PY)"
                    - option "Peru (PE)"
                    - option "Philippines (PH)"
                    - option "Pitcairn (PN)"
                    - option "Poland (PL)"
                    - option "Portugal (PT)"
                    - option "Puerto Rico (PR)"
                    - option "Qatar (QA)"
                    - option "Romania (RO)"
                    - option "Russian Federation (RU)"
                    - option "Rwanda (RW)"
                    - option "Réunion (RE)"
                    - option "Saint Barthélemy (BL)"
                    - option "Saint Helena, Ascension and Tristan da Cunha (SH)"
                    - option "Saint Kitts and Nevis (KN)"
                    - option "Saint Lucia (LC)"
                    - option "Saint Martin (French part) (MF)"
                    - option "Saint Pierre and Miquelon (PM)"
                    - option "Saint Vincent and the Grenadines (VC)"
                    - option "Samoa (WS)"
                    - option "San Marino (SM)"
                    - option "Sao Tome and Principe (ST)"
                    - option "Saudi Arabia (SA)"
                    - option "Senegal (SN)"
                    - option "Serbia (RS)"
                    - option "Seychelles (SC)"
                    - option "Sierra Leone (SL)"
                    - option "Singapore (SG)"
                    - option "Sint Maarten (Dutch part) (SX)"
                    - option "Slovakia (SK)"
                    - option "Slovenia (SI)"
                    - option "Solomon Islands (SB)"
                    - option "Somalia (SO)"
                    - option "South Africa (ZA)"
                    - option "South Georgia and the South Sandwich Islands (GS)"
                    - option "South Sudan (SS)"
                    - option "Spain (ES)"
                    - option "Sri Lanka (LK)"
                    - option "Sudan (SD)"
                    - option "Suriname (SR)"
                    - option "Svalbard and Jan Mayen (SJ)"
                    - option "Swaziland (SZ)"
                    - option "Sweden (SE)"
                    - option "Switzerland (CH)"
                    - option "Syrian Arab Republic (SY)"
                    - option "Taiwan (TW)"
                    - option "Tajikistan (TJ)"
                    - option "Tanzania, United Republic of (TZ)"
                    - option "Thailand (TH)"
                    - option "Timor-Leste (TL)"
                    - option "Togo (TG)"
                    - option "Tokelau (TK)"
                    - option "Tonga (TO)"
                    - option "Trinidad and Tobago (TT)"
                    - option "Tunisia (TN)"
                    - option "Turkey (TR)"
                    - option "Turkmenistan (TM)"
                    - option "Turks and Caicos Islands (TC)"
                    - option "Tuvalu (TV)"
                    - option "Uganda (UG)"
                    - option "Ukraine (UA)"
                    - option "United Arab Emirates (AE)"
                    - option "United Kingdom (GB)"
                    - option "United States (US)"
                    - option "United States Minor Outlying Islands (UM)"
                    - option "Uruguay (UY)"
                    - option "Uzbekistan (UZ)"
                    - option "Vanuatu (VU)"
                    - option "Venezuela, Bolivarian Republic of (VE)"
                    - option "Viet Nam (VN)"
                    - option "Virgin Islands, British (VG)"
                    - option "Virgin Islands, U.S. (VI)"
                    - option "Wallis and Futuna (WF)"
                    - option "Western Sahara (EH)"
                    - option "Yemen (YE)"
                    - option "Zambia (ZM)"
                    - option "Zimbabwe (ZW)"
                    - option "Åland Islands (AX)"
                - generic [ref=e117]:
                  - generic [ref=e118]: Passport expiry
                  - textbox "Passport expiry" [ref=e119]
                - generic [ref=e120]:
                  - generic [ref=e121]: National ID / CNIC (optional)
                  - textbox "National ID / CNIC (optional)" [ref=e122]
            - region "Emergency contact" [ref=e123]:
              - generic [ref=e124]:
                - heading "Emergency contact" [level=3] [ref=e125]
                - paragraph [ref=e126]: Someone we can contact if needed during your trip.
              - generic [ref=e127]:
                - generic [ref=e128]:
                  - generic [ref=e129]: Contact name
                  - textbox "Contact name" [ref=e130]
                - generic [ref=e131]:
                  - generic [ref=e132]: Contact phone
                  - textbox "Contact phone" [ref=e133]
            - button "Save profile" [ref=e135] [cursor=pointer]
        - region "Password" [ref=e136]:
          - generic [ref=e137]:
            - heading "Password" [level=3] [ref=e138]
            - paragraph [ref=e139]: Use a strong, unique password for your account.
          - generic [ref=e140]:
            - generic [ref=e141]:
              - generic [ref=e142]:
                - generic [ref=e143]: Current password
                - textbox "Current password" [ref=e144]
              - generic [ref=e145]:
                - generic [ref=e146]: New password
                - textbox "New password" [ref=e147]
              - generic [ref=e148]:
                - generic [ref=e149]: Confirm password
                - textbox "Confirm password" [ref=e150]
            - button "Update password" [ref=e152] [cursor=pointer]
        - region "Connected accounts" [ref=e153]:
          - generic [ref=e154]:
            - heading "Connected accounts" [level=3] [ref=e155]
            - paragraph [ref=e156]: Link Google to sign in faster on this device.
          - link "Link Google account" [ref=e158] [cursor=pointer]:
            - /url: http://127.0.0.1:8765/auth/google/link
        - region "Advanced account actions Optional · permanent deletion" [ref=e159]:
          - group [ref=e160]:
            - generic "Advanced account actions Optional · permanent deletion" [ref=e161] [cursor=pointer]:
              - generic [ref=e162]: Advanced account actions
              - generic [ref=e163]: Optional · permanent deletion
        - generic [ref=e167]:
          - heading [level=2] [ref=e169]: Delete account?
          - generic [ref=e170]:
            - paragraph [ref=e171]: Enter your password to confirm permanent deletion.
            - text: Password
            - textbox [ref=e172]
          - generic [ref=e173]:
            - button [ref=e174] [cursor=pointer]: Cancel
            - button [ref=e175] [cursor=pointer]: Delete account
```

# Test source

```ts
  28  |       if (
  29  |         text.includes('net::ERR_CONNECTION') ||
  30  |         text.includes('net::ERR_NETWORK_CHANGED') ||
  31  |         text.includes('Failed to load resource: net::')
  32  |       ) {
  33  |         return;
  34  |       }
  35  |       consoleErrors.push(text);
  36  |     }
  37  |   });
  38  | 
  39  |   page.on('response', (response) => {
  40  |     const url = response.url();
  41  |     try {
  42  |       const pageOrigin = new URL(page.url()).origin;
  43  |       if (!url.startsWith(pageOrigin)) {
  44  |         return;
  45  |       }
  46  |     } catch {
  47  |       return;
  48  |     }
  49  |     const status = response.status();
  50  |     if (status >= 500 && !url.includes('favicon')) {
  51  |       networkFailures.push({ url, status });
  52  |     }
  53  |   });
  54  | 
  55  |   return { consoleErrors, networkFailures };
  56  | }
  57  | 
  58  | export async function assertDashboardPage(page: Page, spec: PageSpec, testInfo: TestInfo): Promise<void> {
  59  |   const { consoleErrors, networkFailures } = collectPageSignals(page);
  60  |   const response = await page.goto(spec.path, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  61  |   const status = response?.status() ?? 0;
  62  |   const allowed = Array.isArray(spec.expectStatus) ? spec.expectStatus : [spec.expectStatus ?? 200];
  63  | 
  64  |   if (!allowed.includes(status)) {
  65  |     throw new Error(`Unexpected HTTP ${status} for ${spec.path}`);
  66  |   }
  67  | 
  68  |   await page.locator('body').waitFor({ state: 'visible' });
  69  | 
  70  |   await page
  71  |     .waitForFunction(
  72  |       () => {
  73  |         const images = Array.from(document.images).filter((img) => img.src && !img.src.toLowerCase().includes('.svg'));
  74  |         return images.every((img) => img.complete);
  75  |       },
  76  |       undefined,
  77  |       { timeout: 15_000 },
  78  |     )
  79  |     .catch(() => {});
  80  | 
  81  |   if (spec.shell === 'auto') {
  82  |     await Promise.race([
  83  |       page.locator('#jp-dash-sidebar').first().waitFor({ state: 'visible', timeout: 15_000 }),
  84  |       page.locator('.jp-portal__top').first().waitFor({ state: 'visible', timeout: 15_000 }),
  85  |     ]);
  86  |   } else if (spec.shell) {
  87  |     await page.locator(spec.shell).first().waitFor({ state: 'visible', timeout: 15_000 });
  88  |   }
  89  | 
  90  |   if (spec.heading) {
  91  |     const heading = typeof spec.heading === 'string' ? new RegExp(spec.heading, 'i') : spec.heading;
  92  |     await page.getByRole('heading', { name: heading }).first().waitFor({ state: 'visible', timeout: 15_000 }).catch(() => {
  93  |       // Fallback: any h1 visible
  94  |       return page.locator('h1').first().waitFor({ state: 'visible', timeout: 5_000 });
  95  |     });
  96  |   }
  97  | 
  98  |   const bodyText = await page.locator('body').innerText();
  99  |   for (const leak of forbiddenBrands) {
  100 |     if (bodyText.includes(leak)) {
  101 |       throw new Error(`Forbidden branding leak "${leak}" on ${spec.path}`);
  102 |     }
  103 |   }
  104 | 
  105 |   const overflow = await page.evaluate(() => {
  106 |     const doc = document.documentElement;
  107 |     const body = document.body;
  108 |     return doc.scrollWidth > doc.clientWidth + 2 || body.scrollWidth > body.clientWidth + 2;
  109 |   });
  110 |   if (overflow) {
  111 |     throw new Error(`Horizontal overflow on ${spec.path}`);
  112 |   }
  113 | 
  114 |   const brokenImages = await page.evaluate(() =>
  115 |     Array.from(document.images)
  116 |       .filter((img) => {
  117 |         if (!img.src) {
  118 |           return false;
  119 |         }
  120 |         if (img.src.toLowerCase().includes('.svg')) {
  121 |           return false;
  122 |         }
  123 |         return img.naturalWidth === 0 && img.naturalHeight === 0;
  124 |       })
  125 |       .map((img) => img.src),
  126 |   );
  127 |   if (brokenImages.length > 0) {
> 128 |     throw new Error(`Broken images on ${spec.path}: ${brokenImages.join(', ')}`);
      |           ^ Error: Broken images on /profile: http://jetpk.test/storage/agencies/1/branding/jetpk-qa-9hb.png
  129 |   }
  130 | 
  131 |   const shotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-b', 'screenshots', testInfo.project.name);
  132 |   fs.mkdirSync(shotDir, { recursive: true });
  133 |   const safeName = spec.path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'root';
  134 |   await page.screenshot({ path: path.join(shotDir, `${safeName}.png`), fullPage: true });
  135 | 
  136 |   const report = {
  137 |     path: spec.path,
  138 |     project: testInfo.project.name,
  139 |     status,
  140 |     consoleErrors,
  141 |     networkFailures,
  142 |     brokenImages,
  143 |     horizontalOverflow: overflow,
  144 |   };
  145 | 
  146 |   fs.mkdirSync(auditDir, { recursive: true });
  147 |   fs.appendFileSync(path.join(auditDir, 'page-results.jsonl'), `${JSON.stringify(report)}\n`);
  148 | 
  149 |   if (consoleErrors.length > 0) {
  150 |     throw new Error(`Console errors on ${spec.path}: ${consoleErrors.join(' | ')}`);
  151 |   }
  152 |   if (networkFailures.some((f) => f.status >= 500)) {
  153 |     throw new Error(`5xx network failures on ${spec.path}`);
  154 |   }
  155 | }
  156 | 
  157 | export const adminPages: PageSpec[] = [
  158 |   { path: '/admin', shell: '#jp-dash-sidebar' },
  159 |   { path: '/admin/bookings', shell: '#jp-dash-sidebar' },
  160 |   { path: '/admin/customers', shell: '#jp-dash-sidebar' },
  161 |   { path: '/admin/users', shell: '#jp-dash-sidebar' },
  162 |   { path: '/admin/agents', shell: '#jp-dash-sidebar' },
  163 |   { path: '/admin/api-settings', shell: '#jp-dash-sidebar' },
  164 |   { path: '/admin/api-settings/create?provider=sabre', shell: '#jp-dash-sidebar' },
  165 |   { path: '/admin/api-settings/create?provider=pia_ndc', shell: '#jp-dash-sidebar' },
  166 |   { path: '/admin/api-settings/create?provider=airblue', shell: '#jp-dash-sidebar' },
  167 |   { path: '/admin/group-ticketing', shell: '#jp-dash-sidebar' },
  168 |   { path: '/admin/reports', shell: '#jp-dash-sidebar' },
  169 |   { path: '/admin/accounting/ledger', shell: '#jp-dash-sidebar' },
  170 |   { path: '/admin/ledger', shell: '#jp-dash-sidebar' },
  171 |   { path: '/admin/markups', shell: '#jp-dash-sidebar' },
  172 |   { path: '/admin/support/tickets', shell: '#jp-dash-sidebar' },
  173 |   { path: '/admin/settings/communications', shell: '#jp-dash-sidebar' },
  174 |   { path: '/admin/reports/supplier-diagnostics', shell: '#jp-dash-sidebar' },
  175 |   { path: '/admin/settings', shell: '#jp-dash-sidebar' },
  176 |   { path: '/admin/settings/branding', shell: '#jp-dash-sidebar' },
  177 |   { path: '/admin/settings/media', shell: '#jp-dash-sidebar' },
  178 |   { path: '/admin/page-settings', shell: '#jp-dash-sidebar' },
  179 |   { path: '/admin/page-settings/home', shell: '#jp-dash-sidebar' },
  180 |   { path: '/profile', shell: 'auto' },
  181 | ];
  182 | 
  183 | export const staffPages: PageSpec[] = [
  184 |   { path: '/staff', shell: '#jp-dash-sidebar' },
  185 |   { path: '/staff/bookings', shell: '#jp-dash-sidebar' },
  186 |   { path: '/profile', shell: 'auto' },
  187 | ];
  188 | 
  189 | export const agentPages: PageSpec[] = [
  190 |   { path: '/agent', shell: '.jp-portal__top' },
  191 |   { path: '/agent/bookings', shell: '.jp-portal__top' },
  192 |   { path: '/agent/agency', shell: '.jp-portal__top' },
  193 |   { path: '/profile', shell: 'auto' },
  194 | ];
  195 | 
  196 | export const customerPages: PageSpec[] = [
  197 |   { path: '/customer', shell: '.jp-portal__top' },
  198 |   { path: '/customer/bookings', shell: '.jp-portal__top' },
  199 |   { path: '/customer/support', shell: '.jp-portal__top' },
  200 |   { path: '/profile', shell: 'auto' },
  201 | ];
  202 | 
```