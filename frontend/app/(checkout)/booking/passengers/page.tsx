import { Suspense } from "react";
import PassengersClientPage from "./PassengersClientPage";
import Loading from "./loading";
import { PASSENGERS_EARLY_FETCH_INLINE } from "@/features/standard-booking/utils/passengers-early-fetch-inline";

export default function Page() {
  return (
    <>
      <script
        id="jp-passengers-early-fetch"
        dangerouslySetInnerHTML={{ __html: PASSENGERS_EARLY_FETCH_INLINE }}
      />
      <Suspense fallback={<Loading />}>
        <PassengersClientPage />
      </Suspense>
    </>
  );
}
