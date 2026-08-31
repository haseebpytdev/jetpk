# JP-MOBILE-UX-01 issue ledger

## ISSUE_ID=MUX-01
- SURFACE=Flight Results
- ROUTE=/flights/results
- VIEWPORT=390x844
- ORIENTATION=portrait
- SYMPTOM=Fixed FAB overlapped Book Now (bottom-right)
- USER_IMPACT=Primary CTA hard to tap on phone
- ROOT_CAUSE=FAB and Book Now shared bottom-right corner without lift/inset
- MINIMAL_FIX=Lift FAB on `/flights/results|return|details`; inset result actions `max-lg:pr-16`; increase results `pb-24`
- TEST=Bounding-box overlap after deploy = false; Continue sticky also clear
- ENGINEERING_SHA=c89536afe762a567f1df0dc6e193ee0ba2a8af9f
- DEPLOYED_RUNTIME_SHA=c89536afe762a567f1df0dc6e193ee0ba2a8af9f
- BEFORE_SCREENSHOT=screenshots/flight-results (pre-lift visual description: FAB over Book Now)
- AFTER_SCREENSHOT=screenshots/flight-results/flight-results__390x844__final.png
- RESULT=FIXED

## ISSUE_ID=MUX-02
- SURFACE=Flight fare sheet
- ROUTE=/flights/results (modal/sheet)
- VIEWPORT=390x844
- SYMPTOM=Left-moved FAB overlapped full-width Continue CTA
- USER_IMPACT=Continue obscured
- ROOT_CAUSE=Temporary left-corner FAB experiment
- MINIMAL_FIX=Revert left dock; elevate FAB above sticky CTA instead
- ENGINEERING_SHA=c89536afe762a567f1df0dc6e193ee0ba2a8af9f
- RESULT=FIXED

## ISSUE_ID=MUX-03
- SURFACE=Homepage rails
- SYMPTOM=documentElement scrollWidth > clientWidth from card rails
- USER_IMPACT=Potential sideways scroll perception
- ROOT_CAUSE=Horizontal scroller without overflow-x containment
- MINIMAL_FIX=`overflow-x-clip` wrappers on FullCardRail / DestinationsSection
- ENGINEERING_SHA=b217a56c0d0d618a47c43c75eb6c92b5555938f1
- RESULT=FIXED (body scrollWidth == clientWidth)

## ISSUE_ID=MUX-04
- SURFACE=Traveler form
- SYMPTOM=Two-column fields from `sm` felt cramped on large phones
- MINIMAL_FIX=Stack until `md`
- ENGINEERING_SHA=85dbd1ce03e7f10e82a7e28d72ef5db9e69ca63d
- RESULT=FIXED

## ISSUE_ID=MUX-05
- SURFACE=Customer/Agent/Admin/Staff dashboards
- SYMPTOM=Live `/login` remained on “Preparing secure sign-in…” (inputs disabled) during final sweep
- USER_IMPACT=Could not complete portal responsive visual certification in this run
- ROOT_CAUSE=Secure sign-in prep/CSRF handshake did not enable fields in automation session
- MINIMAL_FIX=Re-proof portals in final assessment with working auth session
- RESULT=REMAINING_BLOCKER

## ISSUE_ID=MUX-06
- SURFACE=Review (full passenger data)
- SYMPTOM=Could not advance travelers→review without completing all required fields in time-boxed run
- RESULT=REMAINING_BLOCKER (missing-session screenshots only + traveler PASS)

## ISSUE_ID=MUX-07
- SURFACE=Group short link public UX
- SYMPTOM=R4 left route/model readiness; full public Group short-link UX not certified here
- RESULT=PARTIAL_ROUTE_READY
