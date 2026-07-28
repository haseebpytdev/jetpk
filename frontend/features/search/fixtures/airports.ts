import type { Airport } from "../types";

/**
 * Fixture airports — replace via AirportSearchService when Laravel endpoint is ready.
 * @see frontend/services/airports.ts
 */
export const AIRPORT_FIXTURES: Airport[] = [
  {
    iata: "ISB",
    name: "Islamabad International Airport",
    city: "Islamabad",
    country: "Pakistan",
    nearby: ["RWP"],
  },
  {
    iata: "RWP",
    name: "Rawalpindi",
    city: "Rawalpindi",
    country: "Pakistan",
    nearby: ["ISB"],
  },
  {
    iata: "LHE",
    name: "Allama Iqbal International Airport",
    city: "Lahore",
    country: "Pakistan",
  },
  {
    iata: "KHI",
    name: "Jinnah International Airport",
    city: "Karachi",
    country: "Pakistan",
  },
  {
    iata: "PEW",
    name: "Bacha Khan International Airport",
    city: "Peshawar",
    country: "Pakistan",
  },
  {
    iata: "MUX",
    name: "Multan International Airport",
    city: "Multan",
    country: "Pakistan",
  },
  {
    iata: "SKT",
    name: "Sialkot International Airport",
    city: "Sialkot",
    country: "Pakistan",
  },
  {
    iata: "UET",
    name: "Quetta International Airport",
    city: "Quetta",
    country: "Pakistan",
  },
  {
    iata: "DXB",
    name: "Dubai International Airport",
    city: "Dubai",
    country: "UAE",
  },
  {
    iata: "AUH",
    name: "Zayed International Airport",
    city: "Abu Dhabi",
    country: "UAE",
  },
  {
    iata: "JED",
    name: "King Abdulaziz International Airport",
    city: "Jeddah",
    country: "Saudi Arabia",
  },
  {
    iata: "RUH",
    name: "King Khalid International Airport",
    city: "Riyadh",
    country: "Saudi Arabia",
  },
  {
    iata: "MCT",
    name: "Muscat International Airport",
    city: "Muscat",
    country: "Oman",
  },
  {
    iata: "DOH",
    name: "Hamad International Airport",
    city: "Doha",
    country: "Qatar",
  },
  {
    iata: "IST",
    name: "Istanbul Airport",
    city: "Istanbul",
    country: "Turkey",
  },
  {
    iata: "LHR",
    name: "Heathrow Airport",
    city: "London",
    country: "United Kingdom",
  },
  {
    iata: "MAN",
    name: "Manchester Airport",
    city: "Manchester",
    country: "United Kingdom",
  },
  {
    iata: "BHX",
    name: "Birmingham Airport",
    city: "Birmingham",
    country: "United Kingdom",
  },
  {
    iata: "JFK",
    name: "John F. Kennedy International Airport",
    city: "New York",
    country: "United States",
  },
  {
    iata: "YYZ",
    name: "Toronto Pearson International Airport",
    city: "Toronto",
    country: "Canada",
  },
];
