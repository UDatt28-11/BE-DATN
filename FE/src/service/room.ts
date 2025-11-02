import { Listing } from "../types/room/room";


let listings: Listing[] = [
    {
        key: "1",
        id: 1,
        name: "Villa Biển Xanh",
        location: "Nha Trang",
        price: 2500000,
        rating: 4.8,
        status: "available",
        image: "https://images.unsplash.com/photo-1505691723518-36a5ac3be353",
        createdAt: "2023-02-01",
        updatedAt: "2024-09-20",
        verified: true,
    },
    {
        key: "2",
        id: 2,
        name: "Homestay Gió Biển",
        location: "Phú Quốc",
        price: 1500000,
        rating: 4.3,
        status: "available",
        image: "https://images.unsplash.com/photo-1512917774080-9991f1c4c750",
        createdAt: "2023-03-10",
        updatedAt: "2024-10-05",
        verified: false,
    },
    {
        key: "3",
        id: 3,
        name: "Nhà Gỗ Tây Bắc",
        location: "Sapa",
        price: 800000,
        rating: 4.5,
        status: "unavailable",
        image: "https://images.unsplash.com/photo-1518780664697-55e3ad937233",
        createdAt: "2023-04-15",
        updatedAt: "2024-09-25",
        verified: true,
    },
];

export const getListings = () => listings;

export const addListing = (listing: Listing) => {
    listings = [listing, ...listings];
};

export const updateListing = (updated: Listing) => {
    listings = listings.map((l) => (l.key === updated.key ? updated : l));
};

export const deleteListing = (key: string) => {
    listings = listings.filter((l) => l.key !== key);
};
