import { Suspense } from "react";
import PassengersClientPage from "./PassengersClientPage";
import Loading from "./loading";

export default function Page() {
  return (
    <Suspense fallback={<Loading />}>
      <PassengersClientPage />
    </Suspense>
  );
}
