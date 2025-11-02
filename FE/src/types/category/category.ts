export interface Category {
    key: string;
    id: number;
    name: string;
    description: string;
    image: string;
    status: "active" | "inactive";
    amenityCount: number;
    homestayCount: number;
    createdAt: string;
    updatedAt: string;
}

export interface Amenity {
    id: number;
    name: string;
    icon: string;
}
