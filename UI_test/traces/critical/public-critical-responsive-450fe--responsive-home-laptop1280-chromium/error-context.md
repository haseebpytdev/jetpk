# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: public-critical-responsive.spec.ts >> guest public critical responsive >> home @ laptop1280
- Location: tests\visual\public-critical-responsive.spec.ts:26:7

# Error details

```
Test timeout of 45000ms exceeded.
```

# Page snapshot

```yaml
- generic [ref=e1]:
  - banner [ref=e2]:
    - generic [ref=e3]:
      - link "JetPakistan" [ref=e4] [cursor=pointer]:
        - /url: /
        - img "JetPakistan" [ref=e5]
      - navigation "Primary" [ref=e6]:
        - link "Home" [ref=e7] [cursor=pointer]:
          - /url: /
        - link "Booking" [ref=e8] [cursor=pointer]:
          - /url: /lookup-booking
        - link "Support" [ref=e9] [cursor=pointer]:
          - /url: /support
        - link "About" [ref=e10] [cursor=pointer]:
          - /url: /about-us
      - generic [ref=e11]:
        - link "Sign in" [ref=e12] [cursor=pointer]:
          - /url: /login
        - button "Register" [ref=e14] [cursor=pointer]:
          - text: Register
          - img [ref=e15]
        - button "Switch day or night theme" [ref=e17] [cursor=pointer]:
          - img [ref=e19]
  - main [ref=e22]:
    - generic [ref=e23]:
      - generic:
        - img
        - img
      - img
      - generic [ref=e24]:
        - heading [level=1]
        - search [ref=e25]:
          - generic [ref=e26]:
            - tablist "Search product" [ref=e27]:
              - tab "Flights" [selected] [ref=e29] [cursor=pointer]:
                - img [ref=e30]
                - text: Flights
              - tab "Groups" [ref=e32] [cursor=pointer]
            - tablist "Trip type" [ref=e33]:
              - tab "Return" [ref=e35] [cursor=pointer]
              - tab "One-way" [selected] [ref=e36] [cursor=pointer]
              - tab "Multi-city" [ref=e37] [cursor=pointer]
          - generic [ref=e40]:
            - generic [ref=e41]:
              - generic [ref=e42]:
                - generic [ref=e43]: From
                - generic [ref=e44]:
                  - img [ref=e45]
                  - combobox "From" [ref=e48] [cursor=pointer]
              - button "Swap origin and destination" [ref=e50] [cursor=pointer]:
                - img [ref=e51]
              - generic [ref=e53]:
                - generic [ref=e54]: To
                - generic [ref=e55]:
                  - img [ref=e56]
                  - combobox "To" [ref=e59] [cursor=pointer]
              - generic [ref=e60]:
                - generic [ref=e61]: Departure
                - generic [ref=e62]:
                  - img [ref=e63]
                  - button "Departure" [active] [ref=e66] [cursor=pointer]:
                    - generic [ref=e67]: Departure
              - generic [ref=e69]:
                - generic [ref=e70]: Travellers
                - generic [ref=e71]:
                  - img [ref=e72]
                  - button "Travellers" [ref=e74] [cursor=pointer]:
                    - generic [ref=e75]: 1 adult · Economy
            - generic [ref=e76]:
              - generic [ref=e77]:
                - generic [ref=e78] [cursor=pointer]:
                  - img [ref=e80]
                  - generic [ref=e82]: Direct flights only
                - generic [ref=e83] [cursor=pointer]:
                  - img [ref=e85]
                  - generic [ref=e87]: Include nearby airports
                - generic [ref=e88] [cursor=pointer]:
                  - img [ref=e90]
                  - generic [ref=e92]: Flexible dates ±1 day
              - generic [ref=e94]:
                - generic [ref=e95]: Search
                - button "Search" [ref=e96] [cursor=pointer]:
                  - img [ref=e97]
                  - generic [ref=e100]: Search
        - button "Scroll to content" [ref=e101] [cursor=pointer]: Scroll
    - generic [ref=e107]:
      - img
  - contentinfo [ref=e109]:
    - generic [ref=e110]:
      - generic [ref=e111]:
        - generic [ref=e112]:
          - link "JetPakistan" [ref=e113] [cursor=pointer]:
            - /url: /
            - img "JetPakistan" [ref=e114]
          - paragraph [ref=e115]: We help you plan and book flights with dedicated support.
          - generic [ref=e116]:
            - generic [ref=e117]:
              - img [ref=e118]
              - text: IATA
            - generic [ref=e120]:
              - img [ref=e121]
              - text: PCAA
            - generic [ref=e123]:
              - img [ref=e124]
              - text: PCI-DSS
        - generic [ref=e127]:
          - heading "Company" [level=4] [ref=e128]
          - link "About us" [ref=e129] [cursor=pointer]:
            - /url: /about-us
          - link "Contact" [ref=e130] [cursor=pointer]:
            - /url: /support
        - generic [ref=e131]:
          - heading "Policies" [level=4] [ref=e132]
          - link "Terms" [ref=e133] [cursor=pointer]:
            - /url: /terms
          - link "Privacy" [ref=e134] [cursor=pointer]:
            - /url: /privacy
        - generic [ref=e135]:
          - heading "Support" [level=4] [ref=e136]
          - link "Help centre" [ref=e137] [cursor=pointer]:
            - /url: /faq
          - link "Manage booking" [ref=e138] [cursor=pointer]:
            - /url: /lookup-booking
        - generic [ref=e139]:
          - heading "B2B & agents" [level=4] [ref=e140]
          - link "Become an agent" [ref=e141] [cursor=pointer]:
            - /url: /agent/register
      - generic [ref=e142]:
        - paragraph [ref=e143]: © 2026 JetPakistan. All rights reserved.
        - link "Contact support" [ref=e145] [cursor=pointer]:
          - /url: /support
          - img [ref=e146]
```