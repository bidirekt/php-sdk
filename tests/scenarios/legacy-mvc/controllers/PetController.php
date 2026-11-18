<?php

class PetController extends Controller
{
    /**
     * Lists every pet of the store.
     *
     * @bidirekt provides GET /pets
     * @bidirekt response 200 Pet[]
     *
     * @return void
     */
    public function index()
    {
        $rows = $this->db->query('SELECT * FROM pets ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->json(array_map(array('Pet', 'fromRow'), $rows));
    }

    /**
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200 Pet
     * @bidirekt response 404
     *
     * @param string $id
     * @return void
     */
    public function show($id)
    {
        $pet = $this->find($id);
        if (!$pet) {
            $this->status(404);
            return;
        }
        $this->json($pet);
    }

    /**
     * Adds a pet to the store.
     *
     * @bidirekt provides POST /pets
     * @bidirekt request NewPet
     * @bidirekt response 201 Pet
     * @bidirekt response 422 ValidationErrors
     *
     * @bidirekt schema NewPet
     * @bidirekt field name type=string
     * @bidirekt field weight type=number
     * @bidirekt field owner_id type=integer
     * @bidirekt field category_id type=integer optional=true nullable=true
     * @bidirekt field tags type=array items=string optional=true
     *
     * @return void
     */
    public function store()
    {
        $input = $this->input();
        $errors = array();
        if (empty($input['name'])) {
            $errors['name'] = 'required';
        }
        if (empty($input['owner_id'])) {
            $errors['owner_id'] = 'required';
        }
        if ($errors) {
            $this->json(new ValidationErrors('The pet is not valid', $errors), 422);
            return;
        }

        $statement = $this->db->prepare('INSERT INTO pets (name, weight, owner_id, category_id) VALUES (?, ?, ?, ?)');
        $statement->execute(array($input['name'], $input['weight'] ?? 0, $input['owner_id'], $input['category_id'] ?? null));
        $this->json($this->find($this->db->lastInsertId()), 201);
    }

    /**
     * @bidirekt provides DELETE /pets/*
     * @bidirekt response 204
     * @bidirekt response 404
     *
     * @param string $id
     * @return void
     */
    public function destroy($id)
    {
        $statement = $this->db->prepare('DELETE FROM pets WHERE id = ?');
        $statement->execute(array($id));
        $this->status($statement->rowCount() ? 204 : 404);
    }

    /**
     * The store's home page, in HTML.
     *
     * @return void
     */
    public function page()
    {
        $rows = $this->db->query('SELECT * FROM pets ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $this->render('pets/list', array('pets' => array_map(array('Pet', 'fromRow'), $rows)));
    }

    /**
     * @param string $id
     * @return Pet|null
     */
    private function find($id)
    {
        $statement = $this->db->prepare('SELECT * FROM pets WHERE id = ?');
        $statement->execute(array($id));
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? Pet::fromRow($row) : null;
    }
}
