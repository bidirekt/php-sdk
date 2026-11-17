<?php

class OwnerController extends Controller
{
    /**
     * @bidirekt provides GET /owners/*
     * @bidirekt response 200 Owner
     * @bidirekt response 404
     *
     * @param string $id
     * @return void
     */
    public function show($id)
    {
        $statement = $this->db->prepare('SELECT * FROM owners WHERE id = ?');
        $statement->execute(array($id));
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->status(404);
            return;
        }
        $owner = Owner::fromRow($row);
        $owner->pets = $this->petsOf($id);
        $this->json($owner);
    }

    /**
     * The pets of one owner.
     *
     * @bidirekt provides GET /owners/*\/pets
     * @bidirekt response 200 Pet[]
     *
     * @param string $id
     * @return void
     */
    public function pets($id)
    {
        $this->json($this->petsOf($id));
    }

    /**
     * @param string $id
     * @return Pet[]
     */
    private function petsOf($id)
    {
        $statement = $this->db->prepare('SELECT * FROM pets WHERE owner_id = ?');
        $statement->execute(array($id));
        return array_map(array('Pet', 'fromRow'), $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
