/** Hearses from upstream/source/transportation.cpp. Index 0 is the starting bicycle. */
export const TRANSPORT = [
  {
    name: "Bicycle",
    price: 4,
    bonus: 0,
    description: "(you start with this, you can trade it in for 2)",
    url: "",
  },
  {
    name: "Budget Hearse",
    price: 12,
    bonus: 2,
    description: "The ever humble Budget Hearse can fulfill all your transportation needs.  Children may laugh and point somewhat, but it sure beats a bike.",
    url: "flash/hearse/hearse1.swf",
  },
  {
    name: "Medalist",
    price: 30,
    bonus: 4,
    description: "The new Medalist very simply is the greatest value available today in the funeral profession. Flowing body lines and a graceful roof make this coach a winner.",
    url: "flash/hearse/hearse2.swf",
  },
  {
    name: "Statesman",
    price: 50,
    bonus: 7,
    description: "The paradigm of contemporary elegance and styling, the Statesman funeral coach gracefully integrates form and function with dramatic design.",
    url: "flash/hearse/hearse3.swf",
  },
  {
    name: "Crown Sovereign",
    price: 80,
    bonus: 10,
    description: "Beautifully proportioned, this new funeral coach reflects the prestige and styling of lasting beauty, an investment in your business future that is as distinguished and distinctive as the service you provide.",
    url: "flash/hearse/hearse4.swf",
  },
];

export function rideOf(player) {
  return TRANSPORT[player?.transport ?? 0] ?? TRANSPORT[0];
}
