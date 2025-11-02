export interface Listing {
    key: string;
    id: number;
    name: string;
    location: string;
    price: number;
    rating: number;
    status: "available" | "unavailable";
    image: string;
    createdAt: string;
    updatedAt: string;
    verified: boolean;
}
