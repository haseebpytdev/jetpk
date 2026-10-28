$key = "$env:USERPROFILE\.ssh\jetpk_contabo_2026_v2"
$remote = "pkjetp@185.215.166.176"
$out = "docs/evidence/jp-airblue-v3-frontend-uat/_raw-component-parity.txt"
ssh -i $key -o BatchMode=yes -o ConnectTimeout=30 $remote @'
APP=/home/pkjetp/jetpk_app
echo PROBE=readonly_component_parity
for f in \
  app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php \
  app/Services/Suppliers/AirBlue/AirBlueOtaResponseNormalizer.php \
  app/Services/Suppliers/AirBlue/AirBlueFlightSearchService.php \
  app/Services/Suppliers/AirBlue/AirBlueOfferPriceService.php \
  app/Data/NormalizedFlightOfferData.php \
  app/Http/Controllers/Frontend/FlightController.php \
  app/Services/FlightSearch/FlightSearchResultStore.php \
  app/Support/FlightSearch/PublicFlightSearchSecurity.php \
  app/Services/Suppliers/AirBlue/AirBlueAmbiguousMutationClassifier.php \
  app/Services/Suppliers/AirBlue/AirBlueTicketingService.php
do
  if [ -f "$APP/$f" ]; then
    h=$(sha256sum "$APP/$f" | awk '{print $1}')
    echo "PROD $h $f"
  else
    echo "PROD MISSING $f"
  fi
done
'@ | Tee-Object -FilePath $out
