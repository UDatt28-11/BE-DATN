export interface Amenity {
    id: number;
    name: string;
    type: "Cơ bản" | "Nâng cao";
    icon: string;
    description: string;
    status: "Hoạt động" | "Ẩn";
    createdAt: string;
    updatedAt: string;
}
